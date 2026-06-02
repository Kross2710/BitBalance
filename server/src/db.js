// MySQL connection pool. Reuses the SAME schema/database as the PHP app —
// migration changes the application layer, not the data layer.
import mysql from 'mysql2/promise';

export const pool = mysql.createPool({
  host: process.env.DB_HOST,
  port: Number(process.env.DB_PORT || 3306),
  database: process.env.DB_NAME,
  user: process.env.DB_USER,
  password: process.env.DB_PASSWORD,
  waitForConnections: true,
  connectionLimit: 10,
  // Mirror PHP's `SET time_zone = '+07:00'` so timestamps match the legacy app.
  timezone: '+07:00',
  dateStrings: true,
});

// Small helper: run a query and return rows only.
export async function query(sql, params = []) {
  const [rows] = await pool.query(sql, params);
  return rows;
}
