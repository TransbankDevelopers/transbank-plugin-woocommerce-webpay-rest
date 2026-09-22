import mysql from "mysql2/promise";

const DB_CONFIG = {
    host: process.env.DB_HOST,
    port: Number.parseInt(process.env.DB_PORT, 10),
    user: process.env.DB_USER,
    password: process.env.DB_PASSWORD,
    database: process.env.DB_NAME
};

let pool;

function getPool() {
    if (!pool) {
        pool = mysql.createPool({
            ...DB_CONFIG,
            waitForConnections: true,
            connectionLimit: 5
        });
    }

    return pool;
}

export async function closePool() {
    if (pool) {
        await pool.end();
        pool = null;
    }
}

const queryScalar = async (sql, params = []) => {
    const [rows] = await getPool().execute(sql, params);

    return rows[0] ? Object.values(rows[0])[0] : null;
};

export async function holdLock(key) {
    const connection = await mysql.createConnection(DB_CONFIG);
    const [rows] = await connection.execute(
        "SELECT GET_LOCK(?, 0) AS acquired",
        [key]
    );
    const acquired = rows[0].acquired === 1;

    if (!acquired) {
        await connection.end();
        throw new Error(`Could not acquire lock for key: ${key}`);
    }

    return {
        release: async () => {
            await connection.execute("SELECT RELEASE_LOCK(?)", [key]);
            await connection.end();
        }
    };
}

export async function isLockHeld(key) {
    const result = await queryScalar("SELECT IS_USED_LOCK(?)", [key]);

    return result !== null;
}

export async function getOrderCountByToken(token) {
    const result = await queryScalar(
        "SELECT COUNT(*) FROM wp_wc_orders WHERE id IN (SELECT order_id FROM wp_webpay_rest_transactions WHERE token = ?) AND type = 'shop_order'",
        [token]
    );

    return Number(result);
}

export async function getTransactionStatus(token) {
    const result = await queryScalar(
        "SELECT status FROM wp_webpay_rest_transactions WHERE token = ? LIMIT 1",
        [token]
    );

    return result;
}
