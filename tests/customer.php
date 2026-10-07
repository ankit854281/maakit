<?php
session_start();
function t($en, $hi) { return $en; }
require_once __DIR__.'/../inc/customer.php';
function customer_check($ok, $msg) { if (!$ok) throw new RuntimeException($msg); }
$p = new PDO('sqlite::memory:');
$p->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$p->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$p->sqliteCreateFunction('NOW', function() { return date('Y-m-d H:i:s'); });
$p->exec('CREATE TABLE customers(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,mobile TEXT UNIQUE,password TEXT,village TEXT,landmark TEXT,active INTEGER DEFAULT 1,last_login TEXT);
CREATE TABLE orders(id INTEGER PRIMARY KEY,customer_id INTEGER,mobile TEXT);
CREATE TABLE service_bookings(id INTEGER PRIMARY KEY,customer_id INTEGER,mobile TEXT)');
$p->exec("INSERT INTO orders VALUES(1,NULL,'9000000001'); INSERT INTO service_bookings VALUES(1,NULL,'9000000001')");
customer_check(!cust_register($p,'Test Customer','9000000001','1234','','')[0], 'Short registration password rejected');
customer_check(!cust_password_ok(str_repeat('x',73)), 'Hash truncation boundary rejected');
customer_check(cust_password_ok(str_repeat('x',72)), 'Maximum supported password accepted');
$before = session_id();
customer_check(cust_register($p,'Test Customer','9000000001','long-test-password','','')[0], 'Registration succeeds');
customer_check(session_id() !== $before, 'Registration rotates session');
$id = cust()['id'];
customer_check($p->query('SELECT customer_id FROM orders WHERE id=1')->fetchColumn() === null, 'Guest order not claimed by phone');
customer_check($p->query('SELECT customer_id FROM service_bookings WHERE id=1')->fetchColumn() === null, 'Guest booking not claimed by phone');
customer_check(!cust_orders($p,$id) && !cust_bookings($p,$id), 'Guest history absent from new account');
$p->exec("INSERT INTO orders VALUES(2,$id,'9000000001'); INSERT INTO service_bookings VALUES(2,$id,'9000000001')");
customer_check(count(cust_orders($p,$id)) === 1 && count(cust_bookings($p,$id)) === 1, 'Signed-in history remains visible');
$before = session_id(); cust_logout();
customer_check(!cust() && session_id() !== $before, 'Logout clears account and rotates session');
customer_check(!cust_login($p,'9000000001','wrong')[0], 'Wrong password rejected');
$before = session_id();
customer_check(cust_login($p,'9000000001','long-test-password')[0] && session_id() !== $before, 'Login rotates session');
cust_logout();
$p->prepare('INSERT INTO customers(name,mobile,password,village,landmark) VALUES(?,?,?,?,?)')->execute(['Legacy','9000000002',password_hash('1234',PASSWORD_DEFAULT),'','']);
customer_check(cust_login($p,'9000000002','1234')[0], 'Existing short-password account still works');
cust_logout();
echo "Customer privacy, history and session checks passed\n";
