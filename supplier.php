<?php
// Include the database connection file
require_once 'db.php';

$message = "";
$messageColor = "green";

// Handle Form Submission (Add New Supplier)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_supplier'])) {
    $supplierId   = trim($_POST['supplierId']);
    $supplierName = trim($_POST['supplierName']);
    $supplierPhone= trim($_POST['supplierPhone']);
    $location     = trim($_POST['location']);

    if (!empty($supplierId) && !empty($supplierName)) {
        // SQL query to insert into Oracle
        $insertQuery = "INSERT INTO supplier (supplierId, supplierName, supplierPhone, location) 
                        VALUES (:id, :name, :phone, :loc)";
        
        $stid = oci_parse($conn, $insertQuery);
        
        // Bind parameters safely to prevent SQL injection errors
        oci_bind_by_name($stid, ":id", $supplierId);
        oci_bind_by_name($stid, ":name", $supplierName);
        oci_bind_by_name($stid, ":phone", $supplierPhone);
        oci_bind_by_name($stid, ":loc", $location);
        
        $execute = oci_execute($stid, OCI_COMMIT_ON_SUCCESS); // Auto-save changes permanently
        
        if ($execute) {
            $message = "✅ Supplier '$supplierName' added successfully!";
            $messageColor = "green";
        } else {
            $e = oci_error($stid);
            $message = "❌ Error adding supplier: " . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
            $messageColor = "red";
        }
        oci_free_statement($stid);
    } else {
        $message = "⚠️ Supplier ID and Name are required fields.";
        $messageColor = "orange";
    }
}

// Fetch Existing Suppliers to display in the data grid
$selectQuery = "SELECT supplierId, supplierName, supplierPhone, location FROM supplier ORDER BY supplierId ASC";
$stid = oci_parse($conn, $selectQuery);
oci_execute($stid);

$suppliers = [];
while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
    $suppliers[] = $row;
}
oci_free_statement($stid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Koti Nursery — Supplier Management</title>
  <style>
    h1 { color: #2e5e34; margin-top: 0; }
    .nav-btn { display: inline-block; background: #2e5e34; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .nav-btn:hover { background: #26512d; }
    .grid { display: flex; gap: 30px; flex-wrap: wrap; }
    .form-container { flex: 1; min-width: 300px; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
    .table-container { flex: 2; min-width: 500px; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
    label { display: block; margin: 12px 0 6px; color: #3f593e; font-weight: bold; }
    input { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
    button { width: 100%; margin-top: 20px; padding: 12px; border: none; border-radius: 6px; background: #2e5e34; color: white; font-size: 16px; cursor: pointer; font-weight: bold; }
    button:hover { background: #26512d; }
    table { width: 100%; border-collapse: collapse; margin-top: 15px; }
    th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
    th { background-color: #2e5e34; color: white; }
    tr:hover { background-color: #f9fbf9; }
    .alert { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: bold; text-align: center; }
  </style>
  <?php include 'sidebar_styles.php'; ?>
</head>
<body>
  <div class="page-shell">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">

      <h1>Supplier Management</h1>
  
  <?php if (!empty($message)): ?>
    <div class="alert" style="background-color: <?php echo $messageColor === 'green' ? '#e2f0d9' : ($messageColor === 'red' ? '#fce4d6' : '#fff2cc'); ?>; color: <?php echo $messageColor; ?>;">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <div class="grid">
    <div class="form-container">
      <h2>Add New Supplier</h2>
      <form action="supplier.php" method="POST">
        <label for="supplierId">Supplier ID</label>
        <input type="text" id="supplierId" name="supplierId" placeholder="e.g. S01" required>

        <label for="supplierName">Supplier Name</label>
        <input type="text" id="supplierName" name="supplierName" placeholder="e.g. Green Valley Nursery" required>

        <label for="supplierPhone">Phone Number</label>
        <input type="text" id="supplierPhone" name="supplierPhone" placeholder="e.g. 015-1234567">

        <label for="location">Location / Region</label>
        <input type="text" id="location" name="location" placeholder="e.g. Cameron Highlands, Pahang">

        <button type="submit" name="add_supplier">Save Supplier</button>
      </form>
    </div>

    <div class="table-container">
      <h2>Koti Nursery Suppliers</h2>
      <table>
        <thead>
          <tr>
            <th>Supplier ID</th>
            <th>Company Name</th>
            <th>Contact Phone</th>
            <th>Location</th>
            <th style="width:110px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($suppliers) > 0): ?>
            <?php foreach ($suppliers as $sup): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($sup['SUPPLIERID'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars($sup['SUPPLIERNAME'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($sup['SUPPLIERPHONE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($sup['LOCATION'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <a href="supplier_edit.php?supplierId=<?php echo urlencode($sup['SUPPLIERID']); ?>" style="display:inline-block;padding:6px 10px;background:#3f7d4b;color:#fff;border-radius:6px;text-decoration:none;font-size:13px">Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align: center; color: #777;">No supplier configurations located in Oracle.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>