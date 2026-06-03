// Loads the schema-only fixture (test/fixtures/schema.sql) into the database
// named by DB_NAME. Used by CI before the integration tests, against a fresh
// throwaway MariaDB service. Uses mysql2 (already a dependency) so it needs no
// system `mysql` client on the runner. Reads DB_* from the environment.
import { readFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import path from 'node:path';
import mysql from 'mysql2/promise';

const dir = path.dirname(fileURLToPath(import.meta.url));
const sql = readFileSync(path.join(dir, 'fixtures', 'schema.sql'), 'utf8');

const conn = await mysql.createConnection({
  host: process.env.DB_HOST || '127.0.0.1',
  port: Number(process.env.DB_PORT || 3306),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || '',
  database: process.env.DB_NAME,
  multipleStatements: true,
});
await conn.query(sql);
await conn.end();
console.log(`Schema fixture loaded into ${process.env.DB_NAME}.`);
