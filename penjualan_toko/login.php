<?php

require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/config/database.php';

if (isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$error = null;
$setupRequired = false;
$adminTableReady = false;
$loginAd = null;

try {
    $adminCount = (int) $pdo->query('SELECT COUNT(*) FROM tbl_admin')->fetchColumn();
    $adminTableReady = true;
    if ($adminCount === 0) {
        header('Location: setup_admin.php');
        exit;
    }

    $loginAd = $pdo->query(
        'SELECT i.judul, i.deskripsi, i.teks_tombol, i.gambar_iklan,
                p.nama_produk, p.harga, p.gambar
         FROM tbl_iklan_dashboard i
         LEFT JOIN tbl_produk p ON p.id_produk = i.id_produk
         WHERE i.id_iklan = 1 AND i.aktif = 1'
    )->fetch() ?: null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $query = $pdo->prepare(
            'SELECT id_admin, username, nama_admin, password_hash
             FROM tbl_admin
             WHERE username = ?
             LIMIT 1'
        );
        $query->execute([$username]);
        $admin = $query->fetch();

        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id_admin'];
            $_SESSION['admin_name'] = $admin['nama_admin'];
            unset($_SESSION['csrf_token']);
            csrfToken();
            header('Location: index.php');
            exit;
        }

        $error = 'Username atau kata sandi salah.';
    }
} catch (InvalidArgumentException $exception) {
    $error = $exception->getMessage();
} catch (PDOException $exception) {
    error_log('Admin login query failed: ' . $exception->getMessage());
    if (!$adminTableReady && (string) $exception->getCode() === '42S02') {
        $setupRequired = true;
        $error = 'Tabel login belum tersedia. Jalankan database_sql/upgrade_admin_and_banner.sql terlebih dahulu.';
    } elseif (in_array((string) $exception->getCode(), ['42S02', '42S22'], true)) {
        $error = 'Struktur iklan belum lengkap. Jalankan database_sql/upgrade_barcode_and_login_ad.sql terlebih dahulu.';
    } else {
        $error = 'Login belum dapat diproses. Periksa koneksi database.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login Admin | MKLOYY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-page">
    <main class="auth-layout">
        <section class="auth-ad-panel">
            <?php if ($loginAd && (!empty($loginAd['gambar_iklan']) || !empty($loginAd['gambar']))): ?>
                <img class="auth-ad-image" src="<?= h($loginAd['gambar_iklan'] ?: $loginAd['gambar']) ?>" alt="<?= h($loginAd['nama_produk'] ?? 'Iklan MKLOYY') ?>">
            <?php else: ?>
                <div class="auth-ad-art" aria-hidden="true"><span>MKLOYY</span><strong>Belanja kebutuhan<br>lebih mudah</strong></div>
            <?php endif; ?>
            <div class="auth-ad-shade"></div>
            <div class="auth-ad-content">
                <span class="auth-ad-kicker">PROMO MKLOYY</span>
                <h2><?= h($loginAd['judul'] ?? 'Selamat datang di MKLOYY') ?></h2>
                <p><?= h($loginAd['deskripsi'] ?? 'Belanja pilihan Anda dengan mudah dan nyaman.') ?></p>
                <?php if (!empty($loginAd['nama_produk'])): ?>
                    <div class="auth-ad-product"><strong><?= h($loginAd['nama_produk']) ?></strong><span><?= h(rupiah($loginAd['harga'])) ?></span></div>
                <?php endif; ?>
                <a class="auth-ad-cta" href="#login-form"><?= h($loginAd['teks_tombol'] ?? 'Masuk sekarang') ?> <span aria-hidden="true">→</span></a>
            </div>
        </section>
        <section class="auth-login-panel">
            <a class="auth-brand" href="login.php"><span class="brand-mark">M</span><span>MKLOYY</span></a>
            <div class="eyebrow">Area administrator</div>
            <h1>Masuk ke akun</h1>
            <p class="auth-description">Masuk untuk mengelola toko, produk, transaksi, dan iklan.</p>
            <?php if ($error): ?>
                <div class="notice error"><?= h($error) ?></div>
                <?php if ($setupRequired): ?><a class="button button-primary auth-submit" href="setup_admin.php">Buka setup admin</a><?php endif; ?>
            <?php endif; ?>
            <?php if (!$setupRequired): ?>
                <form method="post" class="auth-form" id="login-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <label>Username<input name="username" type="text" autocomplete="username" maxlength="50" required autofocus></label>
                    <label>Kata sandi<input name="password" type="password" autocomplete="current-password" required></label>
                    <button class="button button-primary auth-submit" type="submit">Masuk</button>
                </form>
            <?php endif; ?>
            <p class="auth-login-footer">Akses khusus administrator MKLOYY</p>
        </section>
    </main>
</body>
</html>
