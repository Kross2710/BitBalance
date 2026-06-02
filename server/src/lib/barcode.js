// Barcode lookup — ports api/intake/lookup_barcode.php. Checks the local
// barcode_products cache first, falls back to OpenFoodFacts, caches the result,
// and records every scan in barcode_scan_log for coverage metrics.
import { query } from '../db.js';

const OFF_TIMEOUT_MS = 6000;

export class BarcodeError extends Error {}

async function logScan(userId, barcode, result, ms) {
  try {
    await query('INSERT INTO barcode_scan_log (user_id, barcode, result, latency_ms) VALUES (?, ?, ?, ?)', [
      userId,
      barcode,
      result,
      ms,
    ]);
  } catch (e) {
    console.error('barcode_scan_log insert failed:', e.message);
  }
}

function productPayload(row) {
  return {
    found: true,
    barcode: row.barcode,
    product_name: row.product_name,
    brand: row.brand,
    serving_size: row.serving_size,
    kcal_per_serving: row.kcal_per_serving !== null ? Number(row.kcal_per_serving) : null,
    kcal_per_100g: row.kcal_per_100g !== null ? Number(row.kcal_per_100g) : null,
    protein: row.protein_per_serving !== null ? Number(row.protein_per_serving) : null,
    carbs: row.carbs_per_serving !== null ? Number(row.carbs_per_serving) : null,
    fat: row.fat_per_serving !== null ? Number(row.fat_per_serving) : null,
    sugar: row.sugar_per_serving !== null ? Number(row.sugar_per_serving) : null,
    image_url: row.image_url,
    source: row.source,
  };
}

async function fetchOpenFoodFacts(barcode) {
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), OFF_TIMEOUT_MS);
  try {
    const res = await fetch(`https://world.openfoodfacts.org/api/v2/product/${encodeURIComponent(barcode)}.json`, {
      signal: ctrl.signal,
      headers: { 'User-Agent': 'BitBalance/1.0 (calorie tracker)' },
    });
    if (res.status !== 200) throw new BarcodeError(`HTTP ${res.status}`);
    return await res.json();
  } finally {
    clearTimeout(timer);
  }
}

// Returns a product payload object ({ found, ... }). Throws BarcodeError when
// OpenFoodFacts is unreachable (the route maps that to 502).
export async function lookupBarcode(userId, barcode) {
  const tStart = Date.now();

  // 1. Cache.
  const cached = (await query('SELECT * FROM barcode_products WHERE barcode = ? LIMIT 1', [barcode]))[0];
  if (cached) {
    query('UPDATE barcode_products SET lookup_count = lookup_count + 1 WHERE barcode = ?', [barcode]).catch(() => {});
    const ms = Date.now() - tStart;
    await logScan(userId, barcode, 'cache_hit', ms);
    return { ...productPayload(cached), cache_hit: true, latency_ms: ms };
  }

  // 2. OpenFoodFacts fallback.
  let data;
  try {
    data = await fetchOpenFoodFacts(barcode);
  } catch (e) {
    const ms = Date.now() - tStart;
    await logScan(userId, barcode, 'api_error', ms);
    throw new BarcodeError('OpenFoodFacts unreachable: ' + (e.name === 'AbortError' ? 'timeout' : e.message));
  }

  if (!data || data.status !== 1 || !data.product) {
    const ms = Date.now() - tStart;
    await logScan(userId, barcode, 'api_miss', ms);
    return { found: false, barcode, latency_ms: ms };
  }

  // 3. Parse + cache.
  const p = data.product;
  const n = p.nutriments ?? {};
  const name = p.product_name ?? null;
  const brand = p.brands ?? null;
  const servingSize = p.serving_size ?? null;
  const imageUrl = p.image_url ?? p.image_front_url ?? null;

  let kcalServing = n['energy-kcal_serving'] != null ? Math.round(n['energy-kcal_serving']) : null;
  const kcal100 = n['energy-kcal_100g'] != null ? Number(n['energy-kcal_100g']) : null;
  const proteinS = n.proteins_serving != null ? Number(n.proteins_serving) : null;
  const carbS = n.carbohydrates_serving != null ? Number(n.carbohydrates_serving) : null;
  const fatS = n.fat_serving != null ? Number(n.fat_serving) : null;
  const sugarS = n.sugars_serving != null ? Number(n.sugars_serving) : null;

  // Derive per-serving kcal from per-100g if the serving size has a g/ml number.
  if (kcalServing === null && kcal100 !== null && servingSize) {
    const m = /(\d+(?:\.\d+)?)\s*(g|ml)/i.exec(servingSize);
    if (m) kcalServing = Math.round((kcal100 * parseFloat(m[1])) / 100);
  }

  try {
    await query(
      `INSERT INTO barcode_products
        (barcode, product_name, brand, serving_size, kcal_per_serving, kcal_per_100g,
         protein_per_serving, carbs_per_serving, fat_per_serving, sugar_per_serving, image_url, source)
       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'openfoodfacts')`,
      [barcode, name, brand, servingSize, kcalServing, kcal100, proteinS, carbS, fatS, sugarS, imageUrl]
    );
  } catch (e) {
    console.error('barcode_products insert failed:', e.message);
  }

  const ms = Date.now() - tStart;
  await logScan(userId, barcode, 'api_found', ms);
  return {
    found: true,
    barcode,
    product_name: name,
    brand,
    serving_size: servingSize,
    kcal_per_serving: kcalServing,
    kcal_per_100g: kcal100,
    protein: proteinS,
    carbs: carbS,
    fat: fatS,
    sugar: sugarS,
    image_url: imageUrl,
    source: 'openfoodfacts',
    cache_hit: false,
    latency_ms: ms,
  };
}
