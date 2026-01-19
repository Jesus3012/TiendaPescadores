<?php
include 'includes/session.php';
include 'includes/db.php';
include 'includes/header.php';
include 'includes/navbar.php';

/* ================== CONSULTA ================== */
$result = $conn->query("
    SELECT p.*, cb.codigo 
    FROM productos p
    LEFT JOIN codigos_barras cb ON cb.producto_id = p.id
    ORDER BY p.nombre ASC
");
?>

<style>
/* ================== FONDO GENERAL ================== */
.content-wrapper {
    background: linear-gradient(180deg, #FFF4E6, #FFFFFF);
    min-height: 100vh;
    padding: 25px;
    border-radius: 18px 0 0 18px;
}

/* ================== HEADER ================== */
.page-title {
    font-size: 1.9rem;
    font-weight: 700;
    color: #2c2c2c;
}

/* ================== BUSCADOR ================== */
.buscador-box {
    max-width: 360px;
}

#buscador {
    border-radius: 14px 0 0 14px !important;
    border-right: none;
}

.input-group-text {
    border-radius: 0 14px 14px 0 !important;
    background: #111;
    border: none;
}

/* ================== CARD PRODUCTO ================== */
.product-card-pro {
    background: #fff;
    border-radius: 18px;
    overflow: hidden;
    transition: all .35s ease;
    box-shadow: 0 10px 26px rgba(0,0,0,0.08);
    height: 100%;
}

.product-card-pro:hover {
    transform: translateY(-6px);
    box-shadow: 0 18px 45px rgba(0,0,0,0.16);
}

/* ================== IMAGEN ================== */
.product-image-pro {
    width: 100%;
    height: 190px;
    object-fit: cover;
    background: #f4f4f4;
}

/* ================== TEXTO ================== */
.product-title {
    font-size: 1.1rem;
    font-weight: 600;
    color: #222;
}

.product-meta {
    font-size: .9rem;
    color: #666;
}

/* ================== BADGES ================== */
.badge-stock {
    position: absolute;
    top: 14px;
    right: 14px;
    padding: 6px 14px;
    font-size: .75rem;
    border-radius: 20px;
    box-shadow: 0 4px 10px rgba(0,0,0,.15);
}

/* ================== GRID ESTABLE ================== */
.product-card {
    display: block;
}
</style>

<div class="content-wrapper">

    <!-- ================== HEADER ================== -->
    <section class="content-header mb-4">
        <div class="container-fluid">
            <div class="row align-items-center">

                <div class="col-md-6">
                    <h1 class="page-title">
                        <i ></i> Inventario de Productos
                    </h1>
                </div>

                <div class="col-md-6 d-flex justify-content-md-end mt-3 mt-md-0">
                    <div class="input-group buscador-box">
                        <input type="text" id="buscador" class="form-control" placeholder="Buscar producto...">
                        <div class="input-group-append">
                            <span class="input-group-text">
                                <i class="fas fa-search text-white"></i>
                            </span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ================== LISTADO ================== -->
    <section class="content">
        <div class="container-fluid">
            <div class="row" id="listaProductos">

                <?php while ($row = $result->fetch_assoc()): ?>
                    <?php
                        $nombre = htmlspecialchars($row['nombre']);
                        $stock  = (int)$row['cantidad'];
                        $precio = number_format($row['precio_venta'], 2);
                        $codigo = $row['codigo'] ?? '---';
                        $imagen = $row['imagen'] ?: 'uploads/no-image.png';
                    ?>

                    <div class="col-xl-3 col-lg-4 col-md-6 col-sm-6 mb-4 product-card"
                         data-nombre="<?= strtolower($nombre); ?>">

                        <div class="product-card-pro position-relative">

                            <!-- BADGE -->
                            <?php if ($stock == 0): ?>
                                <span class="badge badge-danger badge-stock">Sin stock</span>
                            <?php elseif ($stock <= 5): ?>
                                <span class="badge badge-warning badge-stock">Stock bajo</span>
                            <?php else: ?>
                                <span class="badge badge-success badge-stock">Stock <?= $stock; ?></span>
                            <?php endif; ?>

                            <!-- IMAGEN -->
                            <img src="<?= $imagen; ?>" class="product-image-pro">

                            <!-- INFO -->
                            <div class="p-3">

                                <h5 class="product-title mb-2"><?= $nombre; ?></h5>

                                <div class="product-meta mb-1">
                                    Precio venta:
                                    <span class="text-success font-weight-bold">$<?= $precio; ?></span>
                                </div>

                                <div class="product-meta mb-1">
                                    Stock actual:
                                    <?= $stock > 0
                                        ? "<span class='font-weight-bold text-dark'>$stock</span>"
                                        : "<span class='font-weight-bold text-danger'>Agotado</span>"; ?>
                                </div>

                                <div class="product-meta">
                                    <strong>Código:</strong> <?= $codigo; ?>
                                </div>

                            </div>

                        </div>
                    </div>

                <?php endwhile; ?>

            </div>
        </div>
    </section>

</div>

<!-- ================== BUSCADOR JS ================== -->
<script>
document.getElementById("buscador").addEventListener("input", function () {
    const texto = this.value.toLowerCase();

    document.querySelectorAll(".product-card").forEach(card => {
        const nombre = card.dataset.nombre;
        card.style.display = nombre.includes(texto) ? "" : "none";
    });
});
</script>

<?php include 'includes/footer.php'; ?>
