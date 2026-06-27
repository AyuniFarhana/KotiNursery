<?php
require_once 'db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $productId = isset($_GET['productId']) ? trim($_GET['productId']) : '';
    if (empty($productId)) {
        header('Location: product.php');
        exit;
    }

    $sql = "SELECT p.productId, p.productName, p.stockQty, p.unitPrice, p.supplierId, s.supplierName,
                   pl.plantCategory, pl.lifeSpan, pl.optimalTemp,
                   st.stuffCategory, st.type AS stuffType
            FROM product p
            LEFT JOIN supplier s ON p.supplierId = s.supplierId
            LEFT JOIN plant pl ON p.productId = pl.productId
            LEFT JOIN stuff st ON p.productId = st.productId
            WHERE p.productId = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $productId);
    oci_execute($stid);
    $prod = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS);
    oci_free_statement($stid);

    if (!$prod) {
        header('Location: product.php');
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {
    $productId   = trim($_POST['productId']);
    $productName = trim($_POST['productName']);
    $stockQty    = intval($_POST['stockQty']);
    $unitPrice   = floatval($_POST['unitPrice']);
    $supplierId  = $_POST['supplierId'] ?? null;
    $itemType    = $_POST['itemType'] ?? '';

    if (empty($productId) || empty($productName)) {
        $message = 'Product ID and name required.';
    } else {
        // Update base product
        $update = "UPDATE product SET productName = :name, stockQty = :qty, unitPrice = :price, supplierId = :sup WHERE productId = :id";
        $stid = oci_parse($conn, $update);
        $supParam = ($supplierId === "") ? null : $supplierId;
        oci_bind_by_name($stid, ':name', $productName);
        oci_bind_by_name($stid, ':qty', $stockQty);
        oci_bind_by_name($stid, ':price', $unitPrice);
        oci_bind_by_name($stid, ':sup', $supParam);
        oci_bind_by_name($stid, ':id', $productId);
        $ok = oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
        oci_free_statement($stid);

        if ($ok) {
            // Update subtype based on itemType
            if ($itemType === 'plant') {
                $plantCategory = trim($_POST['plantCategory'] ?? '');
                $lifeSpan = trim($_POST['lifeSpan'] ?? '');
                $optimalTemp = $_POST['optimalTemp'] !== '' ? floatval($_POST['optimalTemp']) : null;
                $u = "MERGE INTO plant tgt USING (SELECT :id AS productId FROM dual) src ON (tgt.productId = src.productId) 
                      WHEN MATCHED THEN UPDATE SET plantCategory = :cat, lifeSpan = :life, optimalTemp = :temp 
                      WHEN NOT MATCHED THEN INSERT (productId, plantCategory, lifeSpan, optimalTemp) VALUES (:id, :cat, :life, :temp)";
                $stid = oci_parse($conn, $u);
                oci_bind_by_name($stid, ':id', $productId);
                oci_bind_by_name($stid, ':cat', $plantCategory);
                oci_bind_by_name($stid, ':life', $lifeSpan);
                oci_bind_by_name($stid, ':temp', $optimalTemp);
                @oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
                oci_free_statement($stid);
            } elseif ($itemType === 'stuff') {
                $stuffCategory = trim($_POST['stuffCategory'] ?? '');
                $stuffType = trim($_POST['stuffType'] ?? '');
                $u = "MERGE INTO stuff tgt USING (SELECT :id AS productId FROM dual) src ON (tgt.productId = src.productId) 
                      WHEN MATCHED THEN UPDATE SET stuffCategory = :cat, type = :typ 
                      WHEN NOT MATCHED THEN INSERT (productId, stuffCategory, type) VALUES (:id, :cat, :typ)";
                $stid = oci_parse($conn, $u);
                oci_bind_by_name($stid, ':id', $productId);
                oci_bind_by_name($stid, ':cat', $stuffCategory);
                oci_bind_by_name($stid, ':typ', $stuffType);
                @oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
                oci_free_statement($stid);
            }

            header('Location: product.php');
            exit;
        } else {
            $e = oci_error($stid);
            $message = 'Error updating product: ' . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
        }
    }

    // Re-fetch product for display
    $sql = "SELECT p.productId, p.productName, p.stockQty, p.unitPrice, p.supplierId, s.supplierName,
                   pl.plantCategory, pl.lifeSpan, pl.optimalTemp,
                   st.stuffCategory, st.type AS stuffType
            FROM product p
            LEFT JOIN supplier s ON p.supplierId = s.supplierId
            LEFT JOIN plant pl ON p.productId = pl.productId
            LEFT JOIN stuff st ON p.productId = st.productId
            WHERE p.productId = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $productId);
    oci_execute($stid);
    $prod = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS);
    oci_free_statement($stid);
}

// Fetch suppliers for dropdown
$supQuery = "SELECT supplierId, supplierName FROM supplier ORDER BY supplierName ASC";
$supStid = oci_parse($conn, $supQuery);
oci_execute($supStid);
$suppliersList = [];
while ($s = oci_fetch_array($supStid, OCI_ASSOC)) {
    $suppliersList[] = $s;
}
oci_free_statement($supStid);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Product</title>
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
    input, select, textarea { width: 100%; padding: 14px 16px; border: 1px solid rgba(91,121,91,0.2); border-radius: 14px; background: #fbfdf9; font-size: 0.96rem; color: #1d2d22; }
    textarea { min-height: 120px; resize: vertical; }
    .subtype-fields { background: rgba(63,125,75,0.05); border: 1px dashed rgba(63,125,75,0.22); border-radius: 16px; padding: 18px; }
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
            <h1>Edit Product</h1>
            <p style="margin:12px 0 0;color:var(--muted);max-width:680px;line-height:1.7;">Update product</p>
          </div>
          <a class="return-link" href="product.php">⬅ Back to Inventory</a>
        </div>

        <?php if (!empty($message)): ?>
          <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" action="product_edit.php" class="form-grid">
          <div>
            <label for="productId">Product ID</label>
            <input type="text" id="productId" name="productId" value="<?php echo htmlspecialchars($prod['PRODUCTID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
          </div>

          <div>
            <label for="productName">Product Name</label>
            <input type="text" id="productName" name="productName" value="<?php echo htmlspecialchars($prod['PRODUCTNAME'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div>
            <label for="stockQty">Stock Quantity</label>
            <input type="number" id="stockQty" name="stockQty" min="0" value="<?php echo htmlspecialchars($prod['STOCKQTY'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div>
            <label for="unitPrice">Unit Price (RM)</label>
            <input type="number" id="unitPrice" name="unitPrice" step="0.01" min="0.01" value="<?php echo htmlspecialchars($prod['UNITPRICE'] ?? 0, ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div>
            <label for="supplierId">Assigned Supplier</label>
            <select id="supplierId" name="supplierId">
              <option value="">Select Supplier</option>
              <?php foreach ($suppliersList as $s): ?>
                <option value="<?php echo htmlspecialchars($s['SUPPLIERID'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo (isset($prod['SUPPLIERID']) && $prod['SUPPLIERID'] === $s['SUPPLIERID']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($s['SUPPLIERNAME'], ENT_QUOTES, 'UTF-8'); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <?php $isPlant = !empty($prod['PLANTCATEGORY']); ?>
          <input type="hidden" name="itemType" value="<?php echo $isPlant ? 'plant' : 'stuff'; ?>">

          <div class="subtype-fields" style="display:<?php echo $isPlant ? 'block' : 'none'; ?>;">
            <h3 style="margin-top:0;color:var(--accent);">Plant Details</h3>
            <label for="plantCategory">Plant Classification</label>
            <input type="text" id="plantCategory" name="plantCategory" value="<?php echo htmlspecialchars($prod['PLANTCATEGORY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <label for="lifeSpan">Lifespan</label>
            <input type="text" id="lifeSpan" name="lifeSpan" value="<?php echo htmlspecialchars($prod['LIFESPAN'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <label for="optimalTemp">Optimal Temp (°C)</label>
            <input type="number" id="optimalTemp" name="optimalTemp" step="0.1" value="<?php echo htmlspecialchars($prod['OPTIMALTEMP'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div class="subtype-fields" style="display:<?php echo $isPlant ? 'none' : 'block'; ?>;">
            <h3 style="margin-top:0;color:#795548;">Material Details</h3>
            <label for="stuffCategory">Material Category</label>
            <input type="text" id="stuffCategory" name="stuffCategory" value="<?php echo htmlspecialchars($prod['STUFFCATEGORY'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
            <label for="stuffType">Material Sub-Type</label>
            <input type="text" id="stuffType" name="stuffType" value="<?php echo htmlspecialchars($prod['STUFFTYPE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <button type="submit" name="save_product">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
