<?php
// Include the database connection file
require_once 'db.php';

$message = "";
$messageColor = "green";

// Handle Form Submission (Add New Product + Subtype details)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $productId     = trim($_POST['productId']);
    $productName   = trim($_POST['productName']);
    $stockQty      = intval($_POST['stockQty']);
    $unitPrice     = floatval($_POST['unitPrice']);
    $supplierId    = $_POST['supplierId']; 
    $itemType      = $_POST['itemType']; // 'plant' or 'stuff'

    // Subtype values
    $plantCategory = trim($_POST['plantCategory'] ?? '');
    $lifeSpan      = trim($_POST['lifeSpan'] ?? '');
    $optimalTemp   = $_POST['optimalTemp'] !== '' ? floatval($_POST['optimalTemp']) : null;
    $stuffCategory = trim($_POST['stuffCategory'] ?? '');
    $stuffType     = trim($_POST['stuffType'] ?? '');

    if (!empty($productId) && !empty($productName) && !empty($itemType)) {
        
        // 1. Insert into SUPERTYPE Table (product)
        $superQuery = "INSERT INTO product (productId, productName, stockQty, unitPrice, supplierId) 
                       VALUES (:id, :name, :qty, :price, :sup_id)";
        
        $superStid = oci_parse($conn, $superQuery);
        $supParam = ($supplierId === "") ? null : $supplierId;

        oci_bind_by_name($superStid, ":id", $productId);
        oci_bind_by_name($superStid, ":name", $productName);
        oci_bind_by_name($superStid, ":qty", $stockQty);
        oci_bind_by_name($superStid, ":price", $unitPrice);
        oci_bind_by_name($superStid, ":sup_id", $supParam);
        
        // Execute without auto-committing yet so we can do both tables together
        $superExecute = oci_execute($superStid, OCI_NO_AUTO_COMMIT);

        if ($superExecute) {
            $subExecute = false;

            // 2. Insert into the matching SUBTYPE Table
            if ($itemType === 'plant') {
                $subQuery = "INSERT INTO plant (productId, plantCategory, lifeSpan, optimalTemp) 
                             VALUES (:id, :cat, :life, :temp)";
                $subStid = oci_parse($conn, $subQuery);
                oci_bind_by_name($subStid, ":id", $productId);
                oci_bind_by_name($subStid, ":cat", $plantCategory);
                oci_bind_by_name($subStid, ":life", $lifeSpan);
                oci_bind_by_name($subStid, ":temp", $optimalTemp);
                $subExecute = oci_execute($subStid, OCI_NO_AUTO_COMMIT);
                oci_free_statement($subStid);
            } else if ($itemType === 'stuff') {
                $subQuery = "INSERT INTO stuff (productId, stuffCategory, type) 
                             VALUES (:id, :cat, :type)";
                $subStid = oci_parse($conn, $subQuery);
                oci_bind_by_name($subStid, ":id", $productId);
                oci_bind_by_name($subStid, ":cat", $stuffCategory);
                oci_bind_by_name($subStid, ":type", $stuffType);
                $subExecute = oci_execute($subStid, OCI_NO_AUTO_COMMIT);
                oci_free_statement($subStid);
            }

            // 3. If both succeeded, save permanently. Otherwise, rollback!
            if ($subExecute) {
                oci_commit($conn);
                $message = "✅ New " . ucfirst($itemType) . " '$productName' added successfully to inventory!";
                $messageColor = "green";
            } else {
                oci_rollback($conn);
                $e = oci_error();
                $message = "❌ Error adding specific type fields. Transaction rolled back: " . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
                $messageColor = "red";
            }
        } else {
            $e = oci_error($superStid);
            if (strpos($e['message'], 'ORA-00001') !== false) {
                $message = "⚠️ Product ID '$productId' already exists in the system!";
                $messageColor = "orange";
            } else {
                $message = "❌ Error saving base product parameters: " . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
                $messageColor = "red";
            }
        }
        oci_free_statement($superStid);
    } else {
        $message = "⚠️ Please fill out all required fields and select an Item Type.";
        $messageColor = "orange";
    }
}

// Fetch Dynamic Supplier Dropdown List
$supQuery = "SELECT supplierId, supplierName FROM supplier ORDER BY supplierName ASC";
$supStid = oci_parse($conn, $supQuery);
oci_execute($supStid);
$suppliersList = [];
while ($supRow = oci_fetch_array($supStid, OCI_ASSOC)) {
    $suppliersList[] = $supRow;
}
oci_free_statement($supStid);

// Fetch joined table data to show both Plants and Materials together in the grid
$selectQuery = "SELECT p.productId, p.productName, p.stockQty, p.unitPrice, s.supplierName,
                       pl.plantCategory, pl.lifeSpan, pl.optimalTemp,
                       st.stuffCategory, st.type AS stuffType
                FROM product p
                LEFT JOIN supplier s ON p.supplierId = s.supplierId 
                LEFT JOIN plant pl ON p.productId = pl.productId
                LEFT JOIN stuff st ON p.productId = st.productId
                ORDER BY p.productId ASC";
$stid = oci_parse($conn, $selectQuery);
oci_execute($stid);

$products = [];
while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
    $products[] = $row;
}
oci_free_statement($stid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Koti Nursery — Inventory Management</title>
  <style>
    h1 { color: #2e5e34; margin-top: 0; }
    .nav-btn { display: inline-block; background: #2e5e34; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .nav-btn:hover { background: #26512d; }
    .grid { display: flex; gap: 30px; flex-wrap: wrap; }
    .form-container { flex: 1; min-width: 320px; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); height: fit-content; }
    .table-container { flex: 2; min-width: 600px; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
    label { display: block; margin: 12px 0 6px; color: #3f593e; font-weight: bold; }
    input, select { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
    button { width: 100%; margin-top: 25px; padding: 12px; border: none; border-radius: 6px; background: #2e5e34; color: white; font-size: 16px; cursor: pointer; font-weight: bold; }
    button:hover { background: #26512d; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
    th, td { padding: 10px; border-bottom: 1px solid #ddd; text-align: left; }
    th { background-color: #2e5e34; color: white; }
    tr:hover { background-color: #f9fbf9; }
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; text-align: center; }
    .subtype-fields { background: #fcfdfe; border: 1px dashed #2e5e34; padding: 15px; border-radius: 8px; margin-top: 15px; display: none; }
    .badge { padding: 4px 8px; border-radius: 4px; font-size: 12px; font-weight: bold; color: white; }
    .bg-plant { background-color: #2e5e34; }
    .bg-stuff { background-color: #795548; }
  </style>
  <?php include 'sidebar_styles.php'; ?>
</head>
<body>
  <div class="page-shell">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">

      <h1>Inventory Management </h1>
  
  <?php if (!empty($message)): ?>
    <div class="alert" style="background-color: <?php echo $messageColor === 'green' ? '#e2f0d9' : ($messageColor === 'red' ? '#fce4d6' : '#fff2cc'); ?>; color: <?php echo $messageColor; ?>;">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <div class="grid">
    <div class="form-container">
      <h2>Add New Inventory Item</h2>
      <form action="product.php" method="POST">
        <label for="productId">Product ID</label>
        <input type="text" id="productId" name="productId" placeholder="e.g. P001" required>

        <label for="productName">Product Name</label>
        <input type="text" id="productName" name="productName" placeholder="e.g. Cactus Pack" required>

        <label for="stockQty">Stock Quantity</label>
        <input type="number" id="stockQty" name="stockQty" min="0" value="0" required>

        <label for="unitPrice">Unit Price (RM)</label>
        <input type="number" id="unitPrice" name="unitPrice" step="0.01" min="0.01" placeholder="0.00" required>

        <label for="supplierId">Assigned Supplier</label>
        <select id="supplierId" name="supplierId">
          <option value="" disabled selected>Select Supplier</option>
          <?php foreach ($suppliersList as $s): ?>
            <option value="<?php echo htmlspecialchars($s['SUPPLIERID'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($s['SUPPLIERNAME'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="itemType">Item Category Type</label>
        <select id="itemType" name="itemType" onchange="toggleSubtypeFields(this.value)" required>
          <option value="" disabled selected>Select Type</option>
          <option value="plant">Plant (Living Inventory)</option>
          <option value="stuff">Stuff (Gardening Material)</option>
        </select>

        <div id="plantFields" class="subtype-fields">
          <h3 style="margin-top:0; color: #2e5e34; font-size:16px;">Plant Specific Details</h3>
          <label for="plantCategory">Plant Classification</label>
          <input type="text" id="plantCategory" name="plantCategory" placeholder="e.g. Succulent, Shrub">

          <label for="lifeSpan">Lifespan Description</label>
          <input type="text" id="lifeSpan" name="lifeSpan" placeholder="e.g. Perennial, 2-3 Years">

          <label for="optimalTemp">Optimal Temp (°C)</label>
          <input type="number" id="optimalTemp" name="optimalTemp" step="0.1" placeholder="e.g. 25.5">
        </div>

        <div id="stuffFields" class="subtype-fields">
          <h3 style="margin-top:0; color: #795548; font-size:16px;">Material Specific Details</h3>
          <label for="stuffCategory">Material Category</label>
          <input type="text" id="stuffCategory" name="stuffCategory" placeholder="e.g. Tools, Soil, Pots">

          <label for="stuffType">Material Sub-Type</label>
          <input type="text" id="stuffType" name="stuffType" placeholder="e.g. Plastic, Organic">
        </div>

        <button type="submit" name="add_product">Save Inventory Item</button>
      </form>
    </div>

    <div class="table-container">
      <h2>Inventory Items</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Item Name</th>
            <th>Stock</th>
            <th>Price</th>
            <th>Type</th>
            <th>Category Specific attributes</th>
            <th style="width:110px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($products) > 0): ?>
            <?php foreach ($products as $prod): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($prod['PRODUCTID'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars($prod['PRODUCTNAME'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($prod['STOCKQTY'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td>RM <?php echo number_format($prod['UNITPRICE'], 2); ?></td>
                <td>
                  <?php if (!empty($prod['PLANTCATEGORY'])): ?>
                    <span class="badge bg-plant">Plant</span>
                  <?php else: ?>
                    <span class="badge bg-stuff">Material</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php if (!empty($prod['PLANTCATEGORY'])): ?>
                    <em>Class:</em> <?php echo htmlspecialchars($prod['PLANTCATEGORY'], ENT_QUOTES, 'UTF-8'); ?><br>
                    <em>Lifespan:</em> <?php echo htmlspecialchars($prod['LIFESPAN'] ?? '-', ENT_QUOTES, 'UTF-8'); ?><br>
                    <em>Temp:</em> <?php echo !empty($prod['OPTIMALTEMP']) ? htmlspecialchars($prod['OPTIMALTEMP'], ENT_QUOTES, 'UTF-8').'°C' : '-'; ?>
                  <?php else: ?>
                    <em>Category:</em> <?php echo htmlspecialchars($prod['STUFFCATEGORY'] ?? '-', ENT_QUOTES, 'UTF-8'); ?><br>
                    <em>Type:</em> <?php echo htmlspecialchars($prod['STUFFTYPE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>
                  <?php endif; ?>
                </td>
                <td>
                  <a href="product_edit.php?productId=<?php echo urlencode($prod['PRODUCTID']); ?>" style="display:inline-block;padding:6px 10px;background:#3f7d4b;color:#fff;border-radius:6px;text-decoration:none;font-size:13px">Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="6" style="text-align: center; color: #777;">No matching inventory components found inside Oracle.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <script>
    // Toggles visibility of fields depending on the inheritance subtype selected
    function toggleSubtypeFields(val) {
        const plantDiv = document.getElementById('plantFields');
        const stuffDiv = document.getElementById('stuffFields');
        
        plantDiv.style.display = 'none';
        stuffDiv.style.display = 'none';
        
        if(val === 'plant') {
            plantDiv.style.display = 'block';
        } else if(val === 'stuff') {
            stuffDiv.style.display = 'block';
        }
    }
  </script>
</body>
</html>