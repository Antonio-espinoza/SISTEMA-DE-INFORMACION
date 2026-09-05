<?php
require_once 'config.php';
require_once 'helpers.php';
requerir_login();

$active = 'productos';

// --- Activar / desactivar (soft delete: nunca se borra físicamente) ---
if (isset($_GET['cambiar_estado'])) {
    $id = (int) $_GET['cambiar_estado'];
    $stmt = $pdo->prepare('SELECT estado FROM productos WHERE id_producto = :id');
    $stmt->execute(['id' => $id]);
    $actual = $stmt->fetchColumn();
    if ($actual !== false) {
        $nuevo = $actual === 'activo' ? 'inactivo' : 'activo';
        $upd = $pdo->prepare('UPDATE productos SET estado = :e WHERE id_producto = :id');
        $upd->execute(['e' => $nuevo, 'id' => $id]);
        flash_set('ok', $nuevo === 'inactivo' ? 'Producto desactivado.' : 'Producto reactivado.');
    }
    header('Location: productos.php' . (isset($_GET['q']) ? '?q=' . urlencode($_GET['q']) : ''));
    exit;
}

// --- Búsqueda por nombre, código o categoría ---
$busqueda   = trim($_GET['q'] ?? '');
$categorias = $pdo->query('SELECT * FROM categorias ORDER BY nombre')->fetchAll();

$sql = 'SELECT p.*, c.nombre AS categoria_nombre
        FROM productos p
        JOIN categorias c ON c.id_categoria = p.id_categoria
        WHERE 1=1';
$params = [];
if ($busqueda !== '') {
    $sql .= ' AND (p.nombre LIKE :q OR p.codigo LIKE :q OR c.nombre LIKE :q)';
    $params['q'] = '%' . $busqueda . '%';
}
$sql .= ' ORDER BY p.estado DESC, c.nombre, p.nombre';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$productos = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Productos · TecnoStock</title>
<link rel="stylesheet" href="assets/style.css">
</head>
<body>

<?php include 'partials/header.php'; ?>
<?php include 'partials/nav.php'; ?>

<div class="wrap">
  <?php flash_mostrar(); ?>

  <div class="panel-head">
    <h1>Productos</h1>
    <a class="btn" href="producto_form.php">+ nuevo producto</a>
  </div>

  <form class="search" method="get">
    <input type="text" name="q" placeholder="busca por nombre, código o categoría..." value="<?= htmlspecialchars($busqueda) ?>">
    <button type="submit" aria-label="Buscar">🔍</button>
  </form>

  <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th>Código</th>
          <th>Producto</th>
          <th>Categoría</th>
          <th>Precio</th>
          <th>Stock</th>
          <th>Estado</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($productos as $p):
          $inactivo  = $p['estado'] === 'inactivo';
          $sinStock  = $p['stock_actual'] == 0;
          $bajoStock = $p['stock_actual'] <= $p['stock_minimo'];
        ?>
          <tr class="<?= $inactivo ? 'inactivo' : '' ?>">
            <td><?= htmlspecialchars($p['codigo']) ?></td>
            <td><?= iconoCategoria($p['categoria_nombre']) ?> <?= htmlspecialchars($p['nombre']) ?></td>
            <td><?= htmlspecialchars($p['categoria_nombre']) ?></td>
            <td><?= money($p['precio']) ?></td>
            <td>
              <span class="badge <?= $sinStock ? 'badge-out' : ($bajoStock ? 'badge-low' : '') ?>">
                <?= (int)$p['stock_actual'] ?>
              </span>
            </td>
            <td>
              <span class="badge <?= $inactivo ? 'badge-inactivo' : '' ?>">
                <?= $inactivo ? 'inactivo' : 'activo' ?>
              </span>
            </td>
            <td class="col-actions">
              <a class="btn-ghost" href="producto_form.php?id=<?= $p['id_producto'] ?>">editar</a>
              <a class="btn-ghost <?= $inactivo ? '' : 'btn-warn' ?>"
                 href="productos.php?cambiar_estado=<?= $p['id_producto'] ?>&q=<?= urlencode($busqueda) ?>"
                 onclick="return confirm('<?= $inactivo ? '¿Reactivar' : '¿Desactivar' ?> «<?= htmlspecialchars($p['nombre'], ENT_QUOTES) ?>»?');">
                <?= $inactivo ? 'reactivar' : 'desactivar' ?>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (count($productos) === 0): ?>
          <tr><td colspan="7" class="empty-row">no encontramos productos con esa búsqueda.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<footer class="foot">
  <span>TecnoStock · sistema de inventario</span>
</footer>

</body>
</html>
