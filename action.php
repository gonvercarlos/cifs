<?php
require 'config.php'; // debe definir $pdo y session_start()

// Helper para mostrar debug como HTML (seguro para dev)
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function write_debug_log($text) {
    @file_put_contents('/tmp/cif_debug.log', "[".date('Y-m-d H:i:s')."] ".$text.PHP_EOL, FILE_APPEND);
}

// CSRF check
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(400);
    echo "<h2>Error</h2><p>Petición inválida (CSRF).</p><p><a href=\"./\">Volver</a></p>";
    write_debug_log("CSRF FAIL - POST: ".json_encode($_POST));
    exit;
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

try {
    write_debug_log("ACTION: {$action} - POST: " . json_encode($_POST));

    if ($action === 'add') {
        $client_id = (int)$_POST['client_id'];
        $cif = trim($_POST['cif']);
        $entidad = trim($_POST['entidad']);
        if ($client_id <= 0 || $cif === '' || $entidad === '') throw new Exception("Datos incompletos.");
        $pdo->beginTransaction();
        $sql1 = "INSERT INTO cifs (cif, entidad) VALUES (:cif, :entidad) RETURNING id";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->bindValue(':cif',$cif,PDO::PARAM_STR);
        $stmt1->bindValue(':entidad',$entidad,PDO::PARAM_STR);
        $stmt1->execute();
        $newId = $stmt1->fetchColumn();
        if (!$newId){ $pdo->rollBack(); throw new Exception("No se pudo crear CIF."); }
        $sql2 = "INSERT INTO clients_cifs (client_id, cif_id) VALUES (:client_id, :cif_id)";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->bindValue(':client_id',$client_id,PDO::PARAM_INT);
        $stmt2->bindValue(':cif_id',$newId,PDO::PARAM_INT);
        $stmt2->execute();
        $pdo->commit();
        header("Location: ./");
        exit;

    } elseif ($action === 'edit') {
        $cif_id = (int)$_POST['cif_id'];
        $cif = trim($_POST['cif']);
        $entidad = trim($_POST['entidad']);
        if ($cif_id <= 0 || $cif === '' || $entidad === '') throw new Exception("Datos incompletos.");
        $sql = "UPDATE cifs SET cif = :cif, entidad = :entidad WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':cif',$cif,PDO::PARAM_STR);
        $stmt->bindValue(':entidad',$entidad,PDO::PARAM_STR);
        $stmt->bindValue(':id',$cif_id,PDO::PARAM_INT);
        $stmt->execute();
        header("Location: ./");
        exit;

    } elseif ($action === 'delete') {
        $cif_id = isset($_POST['cif_id']) ? (int)$_POST['cif_id'] : 0;
        $client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
        if ($cif_id <= 0) throw new Exception("CIF inválido.");

        $pdo->beginTransaction();

        if ($client_id > 0) {
            $del_assoc_sql = "DELETE FROM clients_cifs WHERE client_id = :client_id AND cif_id = :cif_id";
            $del_assoc_stmt = $pdo->prepare($del_assoc_sql);
            $del_assoc_stmt->bindValue(':client_id',$client_id,PDO::PARAM_INT);
            $del_assoc_stmt->bindValue(':cif_id',$cif_id,PDO::PARAM_INT);
            $del_assoc_stmt->execute();
            write_debug_log("EXEC DELETE clients_cifs client_id={$client_id} cif_id={$cif_id}");
        } else {
            $del_assoc_sql = "DELETE FROM clients_cifs WHERE cif_id = :cif_id";
            $del_assoc_stmt = $pdo->prepare($del_assoc_sql);
            $del_assoc_stmt->bindValue(':cif_id',$cif_id,PDO::PARAM_INT);
            $del_assoc_stmt->execute();
            write_debug_log("EXEC DELETE clients_cifs cif_id={$cif_id} (all clients)");
        }

        $count_sql = "SELECT COUNT(*) FROM clients_cifs WHERE cif_id = :cif_id";
        $count_stmt = $pdo->prepare($count_sql);
        $count_stmt->bindValue(':cif_id',$cif_id,PDO::PARAM_INT);
        $count_stmt->execute();
        $remaining = (int)$count_stmt->fetchColumn();
        write_debug_log("COUNT clients_cifs cif_id={$cif_id} => remaining={$remaining}");

        if ($remaining === 0) {
            $del_cif_sql = "DELETE FROM cifs WHERE id = :cif_id";
            $del_cif_stmt = $pdo->prepare($del_cif_sql);
            $del_cif_stmt->bindValue(':cif_id',$cif_id,PDO::PARAM_INT);
            $del_cif_stmt->execute();
            write_debug_log("EXEC DELETE cifs id={$cif_id}");
        } else {
            write_debug_log("NOT deleting cifs id={$cif_id} because remaining={$remaining}");
        }

        $pdo->commit();
        header("Location: ./");
        exit;
    } else {
        throw new Exception("Acción no reconocida.");
    }
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        try { $pdo->rollBack(); } catch (Exception $_) {}
    }
    $msg = $e->getMessage();
    write_debug_log("ERROR action={$action} - " . $msg . " - POST: " . json_encode($_POST));
    echo "<h2>Error</h2><p>" . h($msg) . "</p><p><a href=\"./\">Volver</a></p>";
    exit;
}
