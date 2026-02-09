<?php
require 'config.php'; // debe definir $pdo y llamar a session_start()

// Parámetros de paginación y búsqueda (idéntico al anterior)
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$allowed_per_page = array('100', '200', '500', 'all');
$per_page = isset($_GET['per_page']) ? $_GET['per_page'] : '100';
if (!in_array($per_page, $allowed_per_page)) $per_page = '100';

$is_all = ($per_page === 'all');
$limit = $is_all ? null : (int)$per_page;
$offset = (!$is_all) ? ($page - 1) * $limit : 0;

$has_search = ($q !== '');
$like_q = '%' . $q . '%';

$where_base = "COALESCE(c.deleted, false) IS NOT TRUE AND c.id NOT IN (743)";

try {
    if ($has_search) {
        $count_sql = "
            SELECT COUNT(*) FROM (
                SELECT DISTINCT c.id
                FROM clients c
                LEFT JOIN clients_cifs cc ON c.id = cc.client_id
                LEFT JOIN cifs cf ON cc.cif_id = cf.id
                WHERE {$where_base}
                  AND (
                    c.client ILIKE :q
                    OR cf.cif ILIKE :q
                    OR cf.entidad ILIKE :q
                  )
            ) t
        ";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->bindValue(':q', $like_q, PDO::PARAM_STR);
        $count_stmt->execute();
        $total_clients = (int)$count_stmt->fetchColumn();
    } else {
        $count_sql = "SELECT COUNT(*) FROM clients c WHERE {$where_base}";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->execute();
        $total_clients = (int)$count_stmt->fetchColumn();
    }

    if ($total_clients === 0) {
        $client_rows = array();
    } else {
        if ($has_search) {
            $clients_sql = "
                SELECT DISTINCT c.id AS client_id, c.client
                FROM clients c
                LEFT JOIN clients_cifs cc ON c.id = cc.client_id
                LEFT JOIN cifs cf ON cc.cif_id = cf.id
                WHERE {$where_base}
                  AND (
                    c.client ILIKE :q
                    OR cf.cif ILIKE :q
                    OR cf.entidad ILIKE :q
                  )
                ORDER BY c.client
            ";
            if (!$is_all) $clients_sql .= " LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($clients_sql);
            $stmt->bindValue(':q', $like_q, PDO::PARAM_STR);
            if (!$is_all) {
                $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            }
            $stmt->execute();
            $client_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } else {
            $clients_sql = "SELECT c.id AS client_id, c.client FROM clients c WHERE {$where_base} ORDER BY c.client";
            if (!$is_all) $clients_sql .= " LIMIT :limit OFFSET :offset";
            $stmt = $pdo->prepare($clients_sql);
            if (!$is_all) {
                $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', (int)$offset, PDO::PARAM_INT);
            }
            $stmt->execute();
            $client_rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    $client_ids = array();
    $clients = array();
    foreach ($client_rows as $r) {
        $cid = (int)$r['client_id'];
        $client_ids[] = $cid;
        $clients[$cid] = array('client' => $r['client'], 'cifs' => array());
    }

    if (!empty($client_ids)) {
        $in_placeholders = array();
        $bind_idx = 0;
        foreach ($client_ids as $cid) {
            $ph = ':id' . $bind_idx++;
            $in_placeholders[] = $ph;
        }
        $in_sql = implode(',', $in_placeholders);

        $cifs_sql = "
            SELECT c.id AS client_id, cf.id AS cif_id, cf.cif, cf.entidad
            FROM clients c
            LEFT JOIN clients_cifs cc ON c.id = cc.client_id
            LEFT JOIN cifs cf ON cc.cif_id = cf.id
            WHERE c.id IN ({$in_sql})
            ORDER BY c.client, cf.entidad NULLS LAST
        ";
        $stmt2 = $pdo->prepare($cifs_sql);
        $bind_idx = 0;
        foreach ($client_ids as $cid) {
            $stmt2->bindValue(':id' . $bind_idx, $cid, PDO::PARAM_INT);
            $bind_idx++;
        }
        $stmt2->execute();
        $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $r) {
            $cid = (int)$r['client_id'];
            if (!isset($clients[$cid])) continue;
            if (!empty($r['cif_id'])) {
                $clients[$cid]['cifs'][] = array(
                    'cif_id' => $r['cif_id'],
                    'cif' => $r['cif'],
                    'entidad' => $r['entidad']
                );
            }
        }
    }

} catch (Exception $e) {
    die("Error al consultar la base de datos.");
}

// paginación
$per_page_display = $is_all ? $total_clients : (int)$limit;
$total_pages = ($is_all || $limit === 0) ? 1 : (int)ceil($total_clients / $limit);
if ($total_pages < 1) $total_pages = 1;
if ($page > $total_pages) $page = $total_pages;

function build_url($overrides = array()) {
    $params = array_merge($_GET, $overrides);
    // Use relative URL for query parameters to avoid duplication with reverse proxy
    return '?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Gestión CIFs</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<script src="https://code.jquery.com/jquery-1.12.4.min.js"></script>
<style>
:root{
  --black:#000;
  --dark:#1f2a2e;
  --light:#3b3f42;
  --accent:#314550;
}
body{ font-family:"Ford Antenna", Helvetica, Arial, sans-serif; margin:20px; color:var(--dark); background:#fff; }
h1{ color:var(--black); }
.topbar{ display:flex; justify-content:space-between; align-items:center; margin-bottom:18px; flex-wrap:wrap;}
.search form{ display:flex; align-items:center; }
.search input{ padding:6px 8px; border:1px solid #ccc; border-radius:4px; margin-right:8px; }
.client-card{ border:1px solid #e3e3e3; padding:12px; margin-bottom:12px; border-radius:6px; box-shadow:0 1px 3px rgba(0,0,0,0.04); }
.cif-row{ display:flex; justify-content:space-between; padding:6px 0; border-top:1px dashed #eee; align-items:center; }
.cif-key{ font-weight:600; color:var(--dark); }
.actions button{ margin-left:6px; }
.btn { padding:6px 10px; border-radius:4px; border:none; cursor:pointer; }
.btn-add { background:var(--accent); color:white; }
.btn-edit { background:#fff; border:1px solid var(--accent); color:var(--accent); }
.btn-delete { background:#f8d7da; color:#721c24; border:1px solid #f5c6cb; }
.small{ font-size:0.9em; color:var(--light); }
.pager{ margin-top:10px; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.pager a{ text-decoration:none; padding:6px 8px; border-radius:4px; border:1px solid #ddd; color:var(--dark); }
.pager .current{ background:var(--accent); color:white; border-color:var(--accent); }
.modal-bg{ display:none; position:fixed; top:0;left:0;right:0;bottom:0; background:rgba(0,0,0,0.5); align-items:center; justify-content:center; z-index:9999; }
.modal{ background:white; padding:18px; border-radius:6px; width:360px; max-width:90%; }
.modal input, .modal textarea{ width:100%; padding:8px; margin-bottom:8px; border:1px solid #ccc; border-radius:4px; }
</style>
</head>
<body>
<div class="topbar">
  <div>
    <h1>Gestión CIFs</h1>
    <div class="small">Mostrando clientes por página: <?php echo htmlspecialchars($per_page, ENT_QUOTES, 'UTF-8'); ?></div>
  </div>

  <div>
    <div class="search">
      <form method="get" action="">
        <input type="text" name="q" placeholder="Buscar cliente, CIF o razón social" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
        <select name="per_page">
          <option value="100"<?php echo $per_page === '100' ? ' selected' : ''; ?>>100</option>
          <option value="200"<?php echo $per_page === '200' ? ' selected' : ''; ?>>200</option>
          <option value="500"<?php echo $per_page === '500' ? ' selected' : ''; ?>>500</option>
          <option value="all"<?php echo $per_page === 'all' ? ' selected' : ''; ?>>Todo</option>
        </select>
        <button class="btn" type="submit">Buscar</button>
      </form>
    </div>
  </div>
</div>

<div class="small" style="margin-bottom:12px;">
<?php
$start = ($total_clients === 0) ? 0 : ($is_all ? 1 : ($offset + 1));
$end = $is_all ? $total_clients : min($offset + $limit, $total_clients);
echo "Mostrando <strong>$start</strong> - <strong>$end</strong> de <strong>$total_clients</strong> clientes.";
?>
</div>

<?php if (empty($clients)): ?>
  <div class="small">No se encontraron clientes.</div>
<?php else: ?>
  <?php foreach ($clients as $client_id => $cdata): ?>
    <div class="client-card" data-client-id="<?php echo $client_id; ?>">
      <div style="display:flex; justify-content:space-between; align-items:center;">
        <div>
          <div class="client-name"><?php echo htmlspecialchars($cdata['client'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="small">ID cliente: <?php echo $client_id; ?></div>
        </div>
        <div>
          <button class="btn btn-add" data-action="add" data-client-id="<?php echo $client_id; ?>">+ Añadir CIF</button>
        </div>
      </div>

      <?php if (empty($cdata['cifs'])): ?>
        <div class="small" style="margin-top:8px;">No hay CIFs asociados.</div>
      <?php else: ?>
        <?php foreach ($cdata['cifs'] as $cif): ?>
          <div class="cif-row">
            <div>
              <div class="cif-key"><?php echo htmlspecialchars($cif['cif'], ENT_QUOTES, 'UTF-8'); ?></div>
              <div class="small"><?php echo htmlspecialchars($cif['entidad'], ENT_QUOTES, 'UTF-8'); ?></div>
            </div>
            <div class="actions">
              <button class="btn btn-edit" data-action="edit" data-client-id="<?php echo $client_id; ?>" data-cif-id="<?php echo $cif['cif_id']; ?>" data-cif="<?php echo htmlspecialchars($cif['cif'], ENT_QUOTES, 'UTF-8'); ?>" data-entidad="<?php echo htmlspecialchars($cif['entidad'], ENT_QUOTES, 'UTF-8'); ?>">✎</button>

              <!-- delete envía el formulario clásico a action.php -->
              <button class="btn btn-delete" data-action="delete" data-client-id="<?php echo $client_id; ?>" data-cif-id="<?php echo $cif['cif_id']; ?>">🗑</button>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<!-- paginador -->
<div class="pager">
<?php
if (!$is_all && $total_pages > 1) {
    if ($page > 1) echo '<a href="' . htmlspecialchars(build_url(array('page'=>$page-1)), ENT_QUOTES, 'UTF-8') . '">&laquo; Prev</a>';
    $max_links = 9;
    $start_p = max(1, $page - intval($max_links/2));
    $end_p = min($total_pages, $start_p + $max_links - 1);
    if ($end_p - $start_p + 1 < $max_links) $start_p = max(1, $end_p - $max_links + 1);
    for ($p = $start_p; $p <= $end_p; $p++) {
        if ($p == $page) echo '<span class="current">'.$p.'</span>';
        else echo '<a href="' . htmlspecialchars(build_url(array('page'=>$p)), ENT_QUOTES, 'UTF-8') . '">'.$p.'</a>';
    }
    if ($page < $total_pages) echo '<a href="' . htmlspecialchars(build_url(array('page'=>$page+1)), ENT_QUOTES, 'UTF-8') . '">Next &raquo;</a>';
}
?>
  <div style="margin-left:12px;">
    <form style="display:inline;" method="get" action="">
      <input type="hidden" name="q" value="<?php echo htmlspecialchars($q, ENT_QUOTES, 'UTF-8'); ?>">
      <label class="small">Mostrar:</label>
      <select name="per_page" onchange="this.form.submit()">
        <option value="100"<?php echo $per_page === '100' ? ' selected' : '';?>>100</option>
        <option value="200"<?php echo $per_page === '200' ? ' selected' : '';?>>200</option>
        <option value="500"<?php echo $per_page === '500' ? ' selected' : '';?>>500</option>
        <option value="all"<?php echo $per_page === 'all' ? ' selected' : '';?>>Todo</option>
      </select>
      <noscript><button type="submit">Aplicar</button></noscript>
    </form>
  </div>
</div>

<!-- Modal -->
<div class="modal-bg" id="modal-bg" style="display:none;">
  <div class="modal" id="modal">
    <h3 id="modal-title">Título</h3>
    <!-- action.php procesará add/edit/delete -->
    <form id="modal-form" method="post" action="action.php">
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
      <input type="hidden" name="action" id="form-action" value="">
      <input type="hidden" name="client_id" id="form-client-id" value="">
      <input type="hidden" name="cif_id" id="form-cif-id" value="">
      <div id="modal-body-fields">
        <label>CIF</label>
        <input type="text" name="cif" id="form-cif" maxlength="50" required>
        <label>Razón social</label>
        <input type="text" name="entidad" id="form-entidad" maxlength="255" required>
      </div>

      <div id="modal-msg" style="display:none;">
        <p class="small">¿Confirma que desea borrar este CIF y su razón social para este cliente?</p>
      </div>

      <div style="text-align:right; margin-top:8px;">
        <button type="button" class="btn" id="modal-cancel">Cancelar</button>
        <button type="submit" class="btn btn-add" id="modal-submit">Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
$(function(){
  function openModal(opts){
    $('#modal-title').text(opts.title || 'Formulario');
    $('#form-action').val(opts.action || '');
    $('#form-client-id').val(opts.client_id || '');
    $('#form-cif-id').val(opts.cif_id || '');
    if (opts.showFields) {
      $('#modal-body-fields').show();
      $('#modal-msg').hide();
      $('#form-cif').val(opts.cif || '').prop('required', true);
      $('#form-entidad').val(opts.entidad || '').prop('required', true);
      $('#modal-submit').text('Guardar');
    } else {
      $('#modal-body-fields').hide();
      $('#modal-msg').show();
      $('#form-cif').prop('required', false);
      $('#form-entidad').prop('required', false);
      $('#modal-submit').text('Borrar');
    }
    $('#modal-bg').fadeIn(150);
  }
  function closeModal(){ $('#modal-bg').fadeOut(120); }

  $('[data-action="add"]').click(function(){
    var clientId = $(this).data('client-id');
    openModal({ title:'Añadir CIF', action:'add', client_id: clientId, showFields: true });
  });
  $('[data-action="edit"]').click(function(){
    openModal({
      title:'Editar CIF',
      action:'edit',
      client_id: $(this).data('client-id'),
      cif_id: $(this).data('cif-id'),
      cif: $(this).data('cif'),
      entidad: $(this).data('entidad'),
      showFields: true
    });
  });
  $('[data-action="delete"]').click(function(){
    openModal({
      title:'Eliminar CIF',
      action:'delete',
      client_id: $(this).data('client-id'),
      cif_id: $(this).data('cif-id'),
      showFields: false
    });
  });

  $('#modal-cancel').click(function(e){ e.preventDefault(); closeModal(); });

  // No AJAX: permitimos submit clásico para add/edit/delete
  $('#modal-form').submit(function(){
    // para evitar doble envío cambiamos el texto
    var action = $('#form-action').val();
    if (action === 'delete') {
      $('#modal-submit').text('Borrando...');
    } else {
      $('#modal-submit').text('Enviando...');
    }
    // submit normal; action.php devolverá la página de debug
    return true;
  });
});
</script>

</body>
</html>
