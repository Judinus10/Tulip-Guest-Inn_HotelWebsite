-- Back up the database. Run once before installing the replacement PHP files.
CREATE TABLE IF NOT EXISTS security_audit_events (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 request_id CHAR(32) NOT NULL, actor_id INT NULL,
 event VARCHAR(190) NOT NULL, outcome VARCHAR(30) NOT NULL,
 ip_address VARCHAR(45) NOT NULL, metadata TEXT NOT NULL,
 created_at DATETIME NOT NULL,
 KEY idx_security_audit_created (created_at),
 KEY idx_security_audit_actor (actor_id, created_at),
 KEY idx_security_audit_request (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE payments MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'Payment Pending';
ALTER TABLE bookings MODIFY COLUMN payment_status VARCHAR(40) NOT NULL DEFAULT 'Payment Pending';
ALTER TABLE bookings MODIFY COLUMN status VARCHAR(40) NOT NULL DEFAULT 'Pending';
