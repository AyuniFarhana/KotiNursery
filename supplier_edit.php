<?php
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $supplierId = isset($_GET['supplierId']) ? trim($_GET['supplierId']) : '';
    if (empty($supplierId)) {
        header('Location: supplier.php');
        exit;
    }

    $sql = "SELECT supplierId, supplierName, supplierPhone, location FROM supplier WHERE supplierId = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $supplierId);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS);
    oci_free_statement($stid);

    if (!$row) {
        header('Location: supplier.php');
        exit;
    }

    $supplier = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_supplier'])) {
    $supplierId   = trim($_POST['supplierId']);
    $supplierName = trim($_POST['supplierName']);
    $supplierPhone= trim($_POST['supplierPhone']);
    $location     = trim($_POST['location']);

    if (empty($supplierId) || empty($supplierName)) {
        $message = 'Supplier ID and Name are required.';
    } else {
        $update = "UPDATE supplier SET supplierName = :name, supplierPhone = :phone, location = :loc WHERE supplierId = :id";
        $stid = oci_parse($conn, $update);
        oci_bind_by_name($stid, ':name', $supplierName);
        oci_bind_by_name($stid, ':phone', $supplierPhone);
        oci_bind_by_name($stid, ':loc', $location);
        oci_bind_by_name($stid, ':id', $supplierId);

        $ok = oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
        if ($ok) {
            header('Location: supplier.php');
            exit;
        } else {
            $e = oci_error($stid);
            $message = 'Error updating supplier: ' . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
        }
        oci_free_statement($stid);
    }

    $sql = "SELECT supplierId, supplierName, supplierPhone, location FROM supplier WHERE supplierId = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $supplierId);
    oci_execute($stid);
    $supplier = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS);
    oci_free_statement($stid);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Supplier</title>
  <style>
    :root {
      color-scheme: light;
      --bg: #eef4ea;
      --surface: #ffffff;
      --surface-soft: #f6faf5;
      --text: #23322a;
      --muted: #566b5d;
      --accent: #3f7d4b;
      --accent-soft: #d9ead3;
      --border: rgba(53, 86, 58, 0.16);
    }

    * { box-sizing: border-box; }
    body { margin: 0; min-height: 100vh; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: radial-gradient(circle at top left, rgba(63, 125, 75, 0.1), transparent 28%), linear-gradient(180deg, #f8faf7 0%, var(--bg) 100%); color: var(--text); }
    .page-wrap { min-height: 100vh; display: flex; flex-direction: column; }
    .content { width: min(980px, calc(100% - 40px)); margin: 0 auto; padding: 32px 0 40px; flex: 1; }
    .card { background: var(--surface); border: 1px solid var(--border); border-radius: 28px; box-shadow: 0 20px 40px rgba(37, 78, 39, 0.08); padding: 34px 36px; min-height: calc(100vh - 180px); display: flex; flex-direction: column; }
    .header { display: flex; align-items: center; justify-content: space-between; gap: 18px; flex-wrap: wrap; margin-bottom: 24px; }
    .header h1 { margin: 0; font-size: clamp(1.8rem, 2.4vw, 2.6rem); line-height: 1.05; }
    .return-link { display: inline-flex; align-items: center; gap: 8px; padding: 12px 18px; border-radius: 999px; background: #e9f4e6; color: var(--accent); text-decoration: none; font-weight: 700; transition: transform 0.18s ease, background 0.18s ease; }
    .return-link:hover { transform: translateY(-1px); background: #d8e8d4; }
    .form-grid { display: grid; gap: 18px; flex: 1; }
    label { display: block; margin-bottom: 8px; font-weight: 700; color: var(--text); }
    input, select { width: 100%; padding: 14px 16px; border: 1px solid rgba(91,121,91,0.2); border-radius: 14px; background: #fbfdf9; font-size: 0.96rem; color: #1d2d22; }
    button { margin-top: 12px; align-self: flex-start; padding: 14px 24px; border: none; border-radius: 14px; background: var(--accent); color: #fff; font-weight: 700; cursor: pointer; box-shadow: 0 10px 20px rgba(63,125,75,0.18); transition: transform 0.18s ease, background 0.18s ease; }
    button:hover { transform: translateY(-1px); background: #35663d; }
    .alert { padding: 16px 18px; border-radius: 16px; background: #ffeae8; color: #8a2723; margin-bottom: 18px; border: 1px solid #f3c9c4; }

    @media (max-width: 820px) {
      .content { width: calc(100% - 28px); }
      .card { min-height: auto; padding: 26px; }
    }
  </style>
</head>
<body>
  <div class="page-wrap">
    <div class="content">
      <div class="card">
        <div class="header">
          <div>
            <h1>Edit Supplier</h1>
            <p style="margin:12px 0 0;color:var(--muted);max-width:680px;line-height:1.7;">Update supplier profile</p>
          </div>
          <a class="return-link" href="supplier.php">⬅ Back to Suppliers</a>
        </div>

        <?php if (!empty($message)): ?>
          <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" action="supplier_edit.php" class="form-grid">
          <div>
            <label for="supplierId">Supplier ID</label>
            <input type="text" id="supplierId" value="<?php echo htmlspecialchars($supplier['SUPPLIERID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
          </div>

          <div>
            <label for="supplierName">Supplier Name</label>
            <input type="text" id="supplierName" name="supplierName" value="<?php echo htmlspecialchars($supplier['SUPPLIERNAME'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div>
            <label for="supplierPhone">Phone</label>
            <input type="text" id="supplierPhone" name="supplierPhone" value="<?php echo htmlspecialchars($supplier['SUPPLIERPHONE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div style="grid-column:1/-1;">
            <label for="location">Location</label>
            <input type="text" id="location" name="location" value="<?php echo htmlspecialchars($supplier['LOCATION'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <button type="submit" name="save_supplier">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
