<?php

require __DIR__ . '/includes/helpers.php';
requireLogin();
require __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newImagePath = null;
    $oldImagePath = null;
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $buttonText = trim((string) ($_POST['button_text'] ?? ''));
        $productIdInput = trim((string) ($_POST['product_id'] ?? ''));
        $productId = $productIdInput === '' ? null : filter_var($productIdInput, FILTER_VALIDATE_INT);

        if (preg_match('/^.{1,120}$/us', $title) !== 1
            || preg_match('/^.{1,255}$/us', $description) !== 1
            || preg_match('/^.{1,40}$/us', $buttonText) !== 1
            || ($productIdInput !== '' && (!$productId || $productId < 1))) {
            throw new InvalidArgumentException('Lengkapi judul (maks. 120), deskripsi (maks. 255), tombol (maks. 40), dan produk yang valid.');
        }

        if ($productId !== null) {
            $productExists = $pdo->prepare('SELECT 1 FROM tbl_produk WHERE id_produk = ?');
            $productExists->execute([$productId]);
            if (!$productExists->fetchColumn()) {
                throw new InvalidArgumentException('Produk iklan tidak ditemukan. Pilih produk yang tersedia.');
            }
        }

        $existing = $pdo->query(
            'SELECT gambar_iklan FROM tbl_iklan_dashboard WHERE id_iklan = 1'
        )->fetchColumn();
        $oldImagePath = $existing === false ? null : $existing;
        if (isset($_FILES['ad_image'])
            && ($_FILES['ad_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $newImagePath = storeAdvertisementImage($_FILES['ad_image']);
        }
        $imagePath = $newImagePath
            ?? (isset($_POST['remove_ad_image']) ? null : $oldImagePath);

        $save = $pdo->prepare(
            'INSERT INTO tbl_iklan_dashboard (id_iklan, judul, deskripsi, teks_tombol, gambar_iklan, id_produk, aktif)
             VALUES (1, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                judul = VALUES(judul),
                deskripsi = VALUES(deskripsi),
                teks_tombol = VALUES(teks_tombol),
                gambar_iklan = VALUES(gambar_iklan),
                id_produk = VALUES(id_produk),
                aktif = VALUES(aktif)'
        );
        $save->execute([
            $title,
            $description,
            $buttonText,
            $imagePath,
            $productId,
            isset($_POST['active']) ? 1 : 0,
        ]);
        if ($oldImagePath && $oldImagePath !== $imagePath) {
            removeAdvertisementImage($oldImagePath);
        }
        $newImagePath = null;
        setFlash('success', 'Iklan login dan dashboard berhasil diperbarui.');
    } catch (InvalidArgumentException $exception) {
        removeAdvertisementImage($newImagePath);
        setFlash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Dashboard advertisement update failed: ' . $exception->getMessage());
        removeAdvertisementImage($newImagePath);
        setFlash('error', 'Iklan belum dapat disimpan. Pastikan migrasi iklan sudah diterapkan.');
    } catch (Throwable $exception) {
        error_log('Unexpected dashboard advertisement error: ' . $exception->getMessage());
        removeAdvertisementImage($newImagePath);
        setFlash('error', 'Terjadi kesalahan saat menyimpan iklan.');
    }

    header('Location: admin.php');
    exit;
}

$pageTitle = 'Kelola Iklan';
$activePage = 'admin';
$flash = takeFlash();
$error = null;
$ad = null;
$products = [];

try {
    $ad = $pdo->query(
        'SELECT i.judul, i.deskripsi, i.teks_tombol, i.gambar_iklan, i.id_produk, i.aktif,
                p.nama_produk, p.harga, p.gambar
         FROM tbl_iklan_dashboard i
         LEFT JOIN tbl_produk p ON p.id_produk = i.id_produk
         WHERE i.id_iklan = 1'
    )->fetch();
    $products = $pdo->query(
        'SELECT id_produk, nama_produk, harga, gambar
         FROM tbl_produk
         ORDER BY nama_produk'
    )->fetchAll();
} catch (PDOException $exception) {
    $error = databaseError($exception);
}

if (!$ad) {
    $ad = [
        'judul' => '',
        'deskripsi' => '',
        'teks_tombol' => 'Lihat Produk',
        'gambar_iklan' => null,
        'id_produk' => null,
        'aktif' => 1,
        'nama_produk' => null,
        'harga' => null,
        'gambar' => null,
    ];
}

require __DIR__ . '/includes/header.php';
?>
<section class="page-heading">
    <div>
        <div class="eyebrow">Pengaturan dashboard</div>
        <h1>Kelola Iklan Produk</h1>
        <p>Atur iklan promosi di halaman login dan dashboard.</p>
    </div>
    <a class="button button-secondary" href="index.php">← Kembali ke dashboard</a>
</section>

<?php if ($flash): ?><div class="notice <?= h($flash['type']) ?>"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>

<section class="admin-ad-layout">
    <article class="panel">
        <div class="panel-header"><div><h2>Konten iklan</h2><p class="panel-subtitle">Unggah gambar iklan atau tampilkan foto produk pilihan.</p></div></div>
        <form method="post" enctype="multipart/form-data" class="admin-ad-form">
            <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
            <label>Judul promosi<input name="title" type="text" maxlength="120" value="<?= h($ad['judul']) ?>" required></label>
            <label>Deskripsi<textarea name="description" rows="4" maxlength="255" required><?= h($ad['deskripsi']) ?></textarea></label>
            <label>Teks tombol<input name="button_text" type="text" maxlength="40" value="<?= h($ad['teks_tombol']) ?>" required></label>
            <label>Gambar iklan (JPG/PNG/WEBP · maks. 5 MB)<input name="ad_image" type="file" accept="image/jpeg,image/png,image/webp"></label>
            <?php if (!empty($ad['gambar_iklan'])): ?>
                <label class="admin-ad-toggle"><input type="checkbox" name="remove_ad_image" value="1"> Hapus gambar iklan unggahan</label>
            <?php endif; ?>
            <label>Produk yang dipromosikan
                <select name="product_id">
                    <option value="">Tanpa produk khusus</option>
                    <?php foreach ($products as $product): ?>
                        <option value="<?= (int) $product['id_produk'] ?>" <?= (int) $ad['id_produk'] === (int) $product['id_produk'] ? 'selected' : '' ?>>
                            <?= h($product['nama_produk']) ?> · <?= h(rupiah($product['harga'])) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <?php if (!$products): ?><div class="notice info">Belum ada produk. Tambahkan produk terlebih dahulu di menu Produk.</div><?php endif; ?>
            <label class="admin-ad-toggle"><input type="checkbox" name="active" value="1" <?= (int) $ad['aktif'] === 1 ? 'checked' : '' ?>> Tampilkan iklan di login dan dashboard</label>
            <button class="button button-primary" type="submit">Simpan iklan</button>
        </form>
    </article>
    <article class="panel">
        <div class="panel-header"><div><h2>Pratinjau banner</h2><p class="panel-subtitle">Contoh tampilan promosi di dashboard.</p></div></div>
        <div class="admin-ad-preview">
            <div class="admin-ad-preview-copy">
                <span class="promo-kicker">PROMO MKLOYY</span>
                <h2><?= h($ad['judul']) ?></h2>
                <p><?= h($ad['deskripsi']) ?></p>
                <span class="promo-button"><?= h($ad['teks_tombol']) ?> →</span>
            </div>
            <div class="admin-ad-preview-image">
                <?php if (!empty($ad['gambar_iklan']) || !empty($ad['gambar'])): ?>
                    <img src="<?= h($ad['gambar_iklan'] ?: $ad['gambar']) ?>" alt="<?= h($ad['nama_produk'] ?? 'Produk promosi') ?>">
                <?php else: ?>
                    <div class="promo-placeholder" aria-hidden="true">MKLOYY</div>
                <?php endif; ?>
                <?php if (!empty($ad['nama_produk'])): ?><div class="promo-product-label"><strong><?= h($ad['nama_produk']) ?></strong><span><?= h(rupiah($ad['harga'])) ?></span></div><?php endif; ?>
            </div>
        </div>
    </article>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
