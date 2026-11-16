<?php

require __DIR__ . '/includes/helpers.php';
requireLogin();
require __DIR__ . '/includes/transaction_service.php';
require __DIR__ . '/config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrfToken($_POST['csrf_token'] ?? null);

        $customerId = filter_var($_POST['customer_id'] ?? null, FILTER_VALIDATE_INT);
        $discount = filter_var($_POST['discount_percent'] ?? 0, FILTER_VALIDATE_FLOAT);
        $amountPaid = filter_var($_POST['amount_paid'] ?? 0, FILTER_VALIDATE_FLOAT);
        $paymentMethod = (string) ($_POST['payment_method'] ?? '');
        $cartJson = (string) ($_POST['cart_json'] ?? '');
        $cartInput = json_decode($cartJson, true);

        if (!$customerId || !is_array($cartInput) || !$cartInput || count($cartInput) > 100
            || $discount === false || $discount < 0 || $discount > 100
            || $amountPaid === false || $amountPaid < 0
            || !in_array($paymentMethod, ['Tunai', 'QRIS', 'Transfer'], true)) {
            throw new InvalidArgumentException('Periksa kembali pelanggan, keranjang, diskon, dan metode pembayaran.');
        }

        $cart = [];
        foreach ($cartInput as $item) {
            if (!is_array($item)
                || !isset($item['id'], $item['quantity'])
                || filter_var($item['id'], FILTER_VALIDATE_INT) === false
                || filter_var($item['quantity'], FILTER_VALIDATE_INT) === false
                || (int) $item['id'] < 1
                || (int) $item['quantity'] < 1
                || (int) $item['quantity'] > 100000) {
                throw new InvalidArgumentException('Isi keranjang tidak valid.');
            }
            $productId = (int) $item['id'];
            $cart[$productId] = ($cart[$productId] ?? 0) + (int) $item['quantity'];
        }

        if (count($cart) > 100) {
            throw new InvalidArgumentException('Keranjang maksimal 100 jenis produk.');
        }

        $result = createTransaction(
            $pdo,
            $customerId,
            $cart,
            (float) $discount,
            (float) $amountPaid,
            $paymentMethod
        );
        header('Location: struk.php?id=' . $result['id']);
        exit;
    } catch (InvalidArgumentException $exception) {
        setFlash('error', $exception->getMessage());
    } catch (PDOException $exception) {
        error_log('Transaction save failed: ' . $exception->getMessage());
        setFlash('error', 'Transaksi gagal disimpan. Database melakukan ROLLBACK; periksa koneksi dan struktur database.');
    } catch (Throwable $exception) {
        error_log('Unexpected transaction error: ' . $exception->getMessage());
        setFlash('error', 'Terjadi kesalahan saat menyimpan transaksi. Database melakukan ROLLBACK jika transaksi sempat dimulai.');
    }

    header('Location: transaksi.php');
    exit;
}

$pageTitle = 'Kasir POS';
$activePage = 'transaksi';
$error = null;
$flash = takeFlash();
$customers = [];
$categories = [];
$products = [];
$recent = [];

try {
    $customers = $pdo->query(
        'SELECT id_pelanggan, nama_pelanggan, kota FROM tbl_pelanggan ORDER BY nama_pelanggan'
    )->fetchAll();
    $categories = $pdo->query(
        'SELECT id_kategori, nama_kategori FROM tbl_kategori ORDER BY nama_kategori'
    )->fetchAll();
    ensureProductBarcodes($pdo);
    $products = $pdo->query(
        'SELECT p.id_produk, p.id_kategori, p.nama_produk, p.harga, p.stok, p.gambar, p.barcode, k.nama_kategori
         FROM tbl_produk p
         LEFT JOIN tbl_kategori k ON k.id_kategori = p.id_kategori
         ORDER BY p.nama_produk'
    )->fetchAll();
    $recent = $pdo->query(
        'SELECT t.id_transaksi, t.tanggal, t.total_belanja, p.nama_pelanggan,
                COUNT(d.id_detail) AS jenis_item, COALESCE(SUM(d.jumlah), 0) AS total_item
         FROM tbl_transaksi t
         JOIN tbl_pelanggan p ON p.id_pelanggan = t.id_pelanggan
         LEFT JOIN tbl_detail_transaksi d ON d.id_transaksi = t.id_transaksi
         GROUP BY t.id_transaksi, t.tanggal, t.total_belanja, p.nama_pelanggan
         ORDER BY t.id_transaksi DESC
         LIMIT 5'
    )->fetchAll();
} catch (PDOException $exception) {
    $error = databaseError($exception);
}

require __DIR__ . '/includes/header.php';
?>
<?php if ($flash): ?><div class="notice <?= h($flash['type']) ?> pos-alert"><?= h($flash['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= h($error) ?></div><?php endif; ?>

<section class="pos-heading">
    <div>
        <div class="eyebrow">Point of sale</div>
        <h1>Kasir Toko</h1>
        <p>Pilih produk, masukkan ke keranjang, lalu selesaikan pembayaran.</p>
    </div>
    <div class="pos-heading-meta"><span class="pos-live-dot"></span>Kasir siap melayani <span class="pos-date"><?= date('d M Y') ?></span></div>
</section>

<form method="post" id="pos-form" class="pos-layout" data-pos-form>
    <input type="hidden" name="csrf_token" value="<?= h(csrfToken()) ?>">
    <input type="hidden" name="cart_json" value="[]" data-cart-input>
    <input type="hidden" name="discount_percent" value="0" data-discount-input>
    <input type="hidden" name="amount_paid" value="0" data-paid-input>

    <section class="pos-catalog">
        <div class="pos-toolbar">
            <label class="pos-search">
                <span aria-hidden="true">⌕</span>
                <input type="search" placeholder="Cari atau scan barcode..." aria-label="Cari atau scan barcode produk" data-product-search autofocus>
                <kbd>Enter</kbd>
            </label>
            <div class="pos-catalog-count"><strong><?= count($products) ?></strong> produk</div>
        </div>
        <div class="pos-category-list" aria-label="Filter kategori">
            <button type="button" class="category-chip active" data-category-filter="all">Semua produk</button>
            <?php foreach ($categories as $category): ?>
                <button type="button" class="category-chip" data-category-filter="<?= (int) $category['id_kategori'] ?>"><?= h($category['nama_kategori']) ?></button>
            <?php endforeach; ?>
        </div>

        <?php if (!$products && !$error): ?>
            <div class="pos-empty">
                <div class="empty-cart-icon">▣</div>
                <h2>Belum ada produk</h2>
                <p>Tambahkan produk dan gambar produk sebelum memulai transaksi.</p>
                <a class="button button-primary" href="produk.php">Kelola produk</a>
            </div>
        <?php else: ?>
            <div class="pos-products" data-product-grid>
                <?php foreach ($products as $product): ?>
                    <?php
                    $imageUrl = $product['gambar'] ? $product['gambar'] : '';
                    $searchText = mb_strtolower($product['nama_produk'] . ' ' . ($product['nama_kategori'] ?? ''));
                    ?>
                    <article class="pos-product-card <?= (int) $product['stok'] < 1 ? 'out-of-stock' : '' ?>"
                        data-product-card
                        data-product-id="<?= (int) $product['id_produk'] ?>"
                        data-product-name="<?= h($product['nama_produk']) ?>"
                        data-product-category="<?= (int) ($product['id_kategori'] ?? 0) ?>"
                        data-product-search-text="<?= h($searchText) ?>"
                        data-product-barcode="<?= h($product['barcode']) ?>"
                        data-product-price="<?= h($product['harga']) ?>"
                        data-product-stock="<?= (int) $product['stok'] ?>">
                        <button type="button" class="product-add" data-add-product aria-label="Tambah <?= h($product['nama_produk']) ?>" <?= (int) $product['stok'] < 1 ? 'disabled' : '' ?>>
                            <?php if ($imageUrl): ?>
                                <img src="<?= h($imageUrl) ?>" alt="<?= h($product['nama_produk']) ?>" loading="lazy">
                            <?php else: ?>
                                <span class="product-placeholder" aria-hidden="true"><?= h(mb_strtoupper(mb_substr($product['nama_produk'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <?php if ((int) $product['stok'] > 0): ?><span class="product-add-icon">＋</span><?php endif; ?>
                        </button>
                        <div class="product-card-info">
                            <span class="product-category"><?= h($product['nama_kategori'] ?? 'Umum') ?></span>
                            <h3 title="<?= h($product['nama_produk']) ?>"><?= h($product['nama_produk']) ?></h3>
                            <div class="product-card-bottom">
                                <strong><?= rupiah($product['harga']) ?></strong>
                                <span class="product-stock <?= (int) $product['stok'] < 5 ? 'low' : '' ?>">Stok <?= number_format((int) $product['stok'], 0, ',', '.') ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="pos-empty pos-no-results" data-no-results hidden>
                <div class="empty-cart-icon">⌕</div><h2>Produk tidak ditemukan</h2><p>Coba kata kunci atau kategori lain.</p>
            </div>
        <?php endif; ?>
    </section>

    <aside class="pos-checkout">
        <div class="checkout-card">
            <div class="checkout-header">
                <div><h2>Keranjang</h2><p>Detail pesanan Anda</p></div>
                <span class="cart-count" data-cart-count>0</span>
            </div>
            <div class="checkout-customer field">
                <label for="pos-customer">Pelanggan</label>
                <select id="pos-customer" name="customer_id" required>
                    <option value="">Pilih pelanggan</option>
                    <?php foreach ($customers as $customer): ?>
                        <option value="<?= (int) $customer['id_pelanggan'] ?>"><?= h($customer['nama_pelanggan']) ?> · <?= h($customer['kota']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cart-items" data-cart-items>
                <div class="cart-empty">
                    <div class="empty-cart-icon">🛒</div>
                    <strong>Keranjang masih kosong</strong>
                    <span>Pilih produk untuk memulai pesanan</span>
                </div>
            </div>
            <div class="checkout-details">
                <div class="discount-row">
                    <label for="pos-discount">Diskon</label>
                    <div class="discount-input-wrap"><input id="pos-discount" type="number" min="0" max="100" step="1" value="0" data-discount-percent><span>%</span></div>
                </div>
                <div class="checkout-line"><span>Subtotal</span><strong data-subtotal>Rp 0</strong></div>
                <div class="checkout-line"><span>Diskon</span><strong class="discount-value" data-discount-value>− Rp 0</strong></div>
                <div class="checkout-line"><span>Pajak 11%</span><strong data-tax>Rp 0</strong></div>
                <div class="checkout-total"><span>Total pembayaran</span><strong data-total>Rp 0</strong></div>
                <div class="payment-methods">
                    <label class="payment-method active"><input type="radio" name="payment_method" value="Tunai" checked data-payment-method><span class="payment-method-icon">▤</span>Tunai</label>
                    <label class="payment-method"><input type="radio" name="payment_method" value="QRIS" data-payment-method><span class="payment-method-icon">▦</span>QRIS</label>
                    <label class="payment-method"><input type="radio" name="payment_method" value="Transfer" data-payment-method><span class="payment-method-icon">⇄</span>Transfer</label>
                </div>
                <div class="field cash-field">
                    <label for="cash-received">Uang diterima</label>
                    <input id="cash-received" type="number" min="0" step="1" placeholder="Masukkan nominal pembayaran" data-cash-input>
                    <div class="quick-cash">
                        <button type="button" data-cash-shortcut="exact">Uang pas</button>
                        <button type="button" data-cash-shortcut="50000">+50 rb</button>
                        <button type="button" data-cash-shortcut="100000">+100 rb</button>
                    </div>
                </div>
                <div class="checkout-line change-line"><span>Kembalian</span><strong data-change>Rp 0</strong></div>
                <button class="button button-primary checkout-submit" type="submit" data-checkout-button disabled>
                    <span>Bayar & Cetak Struk</span><span aria-hidden="true">→</span>
                </button>
                <p class="secure-note"><span>✓</span> Transaksi tersimpan aman dan stok diperbarui otomatis</p>
            </div>
        </div>
        <div class="pos-recent panel">
            <div class="pos-recent-head"><strong>Transaksi terakhir</strong><a href="laporan.php">Semua</a></div>
            <?php if (!$recent): ?><div class="pos-recent-empty">Belum ada transaksi</div>
            <?php else: foreach ($recent as $sale): ?>
                <a class="recent-sale" href="struk.php?id=<?= (int) $sale['id_transaksi'] ?>">
                    <span class="recent-sale-icon">✓</span>
                    <span class="recent-sale-info"><strong><?= h($sale['nama_pelanggan']) ?></strong><small>#<?= (int) $sale['id_transaksi'] ?> · <?= (int) $sale['total_item'] ?> item</small></span>
                    <span class="recent-sale-total"><?= rupiah($sale['total_belanja']) ?></span>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </aside>
</form>
<?php require __DIR__ . '/includes/footer.php'; ?>
