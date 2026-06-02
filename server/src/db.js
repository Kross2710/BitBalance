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
  // Affects only JS<->SQL Date conversion, NOT the server session time_zone.
  timezone: '+07:00',
  dateStrings: true,
});

// mysql2's `timezone` option does NOT issue `SET time_zone`, so NOW()/CURDATE()/
// CURRENT_TIMESTAMP run in the DB host's timezone. The app computes "today" at
// +07:00 (todayVN, Asia/Ho_Chi_Minh) and must match the DB. Force every pooled
// connection to +07:00 — the exact equivalent of PHP db_config.php's
// `SET time_zone='+07:00'`. Without this, on a host in another zone (e.g. the
// CachyOS box on AEST) new intakeLog rows (DEFAULT current_timestamp) land on a
// different date than the day the UI is viewing, so logged food appears to vanish.
pool.on('connection', (conn) => {
  conn.query("SET time_zone = '+07:00'");
});

// Small helper: run a query and return rows only.
export async function query(sql, params = []) {
  const [rows] = await pool.query(sql, params);
  return rows;
}
