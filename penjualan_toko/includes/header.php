<?php

$pageTitle = $pageTitle ?? 'MKLOYY';
$activePage = $activePage ?? '';
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?> | MKLOYY</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-shell">
    <aside class="sidebar" id="sidebar">
        <a class="brand" href="index.php"><span class="brand-mark">M</span><span>MKLOYY</span></a>
        <div class="sidebar-label">MENU UTAMA</div>
        <nav class="side-nav" aria-label="Navigasi utama">
            <a class="<?= $activePage === 'dashboard' ? 'active' : '' ?>" href="index.php"><span class="nav-icon">▦</span>Dashboard</a>
            <a class="<?= $activePage === 'produk' ? 'active' : '' ?>" href="produk.php"><span class="nav-icon">▣</span>Produk</a>
            <a class="<?= $activePage === 'pelanggan' ? 'active' : '' ?>" href="pelanggan.php"><span class="nav-icon">♙</span>Pelanggan</a>
            <a class="<?= $activePage === 'transaksi' ? 'active' : '' ?>" href="transaksi.php"><span class="nav-icon">▣</span>Kasir POS</a>
            <a class="<?= $activePage === 'laporan' ? 'active' : '' ?>" href="laporan.php"><span class="nav-icon">▤</span>Laporan</a>
            <a class="<?= $activePage === 'admin' ? 'active' : '' ?>" href="admin.php"><span class="nav-icon">✎</span>Kelola Iklan</a>
        </nav>
        <div class="sidebar-bottom">
            <div class="sidebar-label">DATABASE</div>
            <div class="database-status"><span class="status-dot"></span>PHP Native · PDO</div>
            <div class="sidebar-note">Database: db_tokokita</div>
        </div>
    </aside>
    <div class="main-shell">
        <header class="topbar">
            <button type="button" class="menu-toggle" data-menu-toggle aria-label="Buka navigasi">☰</button>
            <div class="breadcrumb">MKLOYY <span>/</span> <?= h($pageTitle) ?></div>
            <div class="topbar-right">
                <span class="today"><?= date('d M Y') ?></span>
                <span class="avatar"><?= h(strtoupper(substr((string) $_SESSION['admin_name'], 0, 1))) ?></span>
                <span class="admin-label"><?= h($_SESSION['admin_name']) ?></span>
                <form method="post" action="logout.php" class="logout-form">
                    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
                    <button class="logout-button" type="submit">Keluar</button>
                </form>
            </div>
        </header>
        <main class="content">
