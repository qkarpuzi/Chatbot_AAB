# ChatBot_aab
E kemi krijuar folderin "database" ku kemi bere Export database "aab_chatbot_db.sql" nga phpmyadmin dhe kemi bere lidhje me database ne te njejtin folder e kemi bere lidhjen me database db.php.

password i supabase: 8u6t2b7m8408qQOS










Si ta migrojmë projektin nga XAMPP (MySQL) në Supabase (PostgreSQL)
Ky udhëzues është për fillestarë. Çdo hap është shpjeguar me detaje.
Hapi 1: Aktivizo PostgreSQL në XAMPP

Hap XAMPP Control Panel (si Administrator).
Kliko Config pranë Apache → zgjidh PHP (php.ini).
Shko deri në fund të file-it dhe ngjit këtë kod:

ini; ====================== SUPABASE POSTGRESQL ======================
extension_dir = "C:\xampp\php\ext"
extension=pgsql
extension=pdo_pgsql
; ================================================================

Ruaje file-in (Ctrl + S).
Në XAMPP Control Panel:
Stop Apache
Start Apache sërish

Kontrollo nëse u aktivizua:
Hap në shfletues: http://localhost/ChatBot_aab/database/info.php
Shtyp Ctrl + F dhe shkruaj pgsql
Duhet të shohësh pdo_pgsql dhe pgsql si enabled.



Hapi 2: Krijo lidhjen me Supabase (db.php)
Brenda folderit ChatBot_aab/database/ krijo ose ndrysho file-in db.php dhe ngjit këtë kod:
PHP<?php
// ================== CONFIGURIMI I SUPABASE ==================
$host = 'aws-0-eu-west-1.pooler.supabase.com';
$port = '5432';
$db   = 'postgres';
$user = 'postgres.vvnjnnrfiamqwhateovt'; 
$pass = 'F#x6$bmA&mfMZvs';

$dsn = "pgsql:host=$host;port=$port;dbname=$db;sslmode=require";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
    // echo "✅ Lidhur me Supabase!";
} 
catch (\PDOException $e) {
    die("❌ Gabim lidhje: " . $e->getMessage());
}
?>

Hapi 3: Testo lidhjen

Në të njëjtin folder (database/) krijo file test.php:

PHP<?php
require_once 'db.php';

try {
    $stmt = $pdo->query("SELECT version() as ver");
    $row = $stmt->fetch();
    
    echo "<h2 style='color:green'>✅ LIDHJA U KRYE ME SUKSES!</h2>";
    echo "<strong>Version:</strong> " . $row['ver'];
} 
catch (Exception $e) {
    echo "<h2 style='color:red'>❌ GABIM:</h2>" . $e->getMessage();
}
?>

Hap në shfletues këtë adresë:

http://localhost/ChatBot_aab/database/test.php
Nëse shfaqet "LIDHJA U KRYE ME SUKSES", urime! Projekti është gati.

Hapi 4: Si të përdorni $pdo në faqet e tjera
Në çdo file PHP (index.php, form.php, chat.php etj.) shtoni në fillim:
PHP<?php
require_once 'database/db.php';   // ose '../database/db.php' nëse jeni në një nën-folder
?>
Pastaj mund të përdorni $pdo për të bërë queries.
Shembull query:
PHP$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();

Shënime të rëndësishme

Mos e fikni Apache kur punoni.
MySQL mund ta lini të fikur (nuk ju nevojitet më).
Në PostgreSQL emrat e tabelave dhe kolonave janë case-sensitive.
Nëse keni probleme me queries, na tregoni që t’ju ndihmojmë t’i konvertojmë.    