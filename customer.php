<?php
// Include the database connection file
require_once 'db.php';

$message = "";
$messageColor = "green";

// Handle Form Submission (Add New Customer)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_customer'])) {
    $customerID  = trim($_POST['customerID']);
    $custName    = trim($_POST['custName']);
    $custPhone   = trim($_POST['custPhone']);
    $custType    = trim($_POST['custType']);
    $custAddress = trim($_POST['custAddress']);

    if (!empty($customerID) && !empty($custName)) {
        // SQL query to insert into Oracle
        $insertQuery = "INSERT INTO customer (customerID, custName, custPhone, custType, custAddress) 
                        VALUES (:id, :name, :phone, :ctype, :addr)";
        
        $stid = oci_parse($conn, $insertQuery);
        
        // Bind parameters safely to prevent SQL injection
        oci_bind_by_name($stid, ":id", $customerID);
        oci_bind_by_name($stid, ":name", $custName);
        oci_bind_by_name($stid, ":phone", $custPhone);
        oci_bind_by_name($stid, ":ctype", $custType);
        oci_bind_by_name($stid, ":addr", $custAddress);
        
        $execute = oci_execute($stid, OCI_COMMIT_ON_SUCCESS); // Auto-commits to save permanently
        
        if ($execute) {
            $message = "✅ Customer '$custName' added successfully!";
            $messageColor = "green";
        } else {
            $e = oci_error($stid);
            $message = "❌ Error adding customer: " . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
            $messageColor = "red";
        }
        oci_free_statement($stid);
    } else {
        $message = "⚠️ Customer ID and Name are required fields.";
        $messageColor = "orange";
    }
}

// Fetch Existing Customers to display in the table
$selectQuery = "SELECT customerID, custName, custPhone, custType, custAddress FROM customer ORDER BY customerID ASC";
$stid = oci_parse($conn, $selectQuery);
oci_execute($stid);

$customers = [];
while ($row = oci_fetch_array($stid, OCI_ASSOC)) {
    $customers[] = $row;
}
oci_free_statement($stid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Koti Nursery — Customer Management</title>
  <style>
    h1 { color: #2e5e34; margin-top: 0; }
    .nav-btn { display: inline-block; background: #2e5e34; color: white; padding: 10px 15px; text-decoration: none; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
    .nav-btn:hover { background: #26512d; }
    .grid { display: flex; gap: 30px; flex-wrap: wrap; }
    .form-container { flex: 1; min-width: 300px; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
    .table-container { flex: 2; min-width: 500px; background: white; padding: 25px; border-radius: 12px; box-shadow: 0 3px 10px rgba(0,0,0,0.05); }
    label { display: block; margin: 12px 0 6px; color: #3f593e; font-weight: bold; }
    input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ced4da; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
    textarea { resize: vertical; height: 80px; }
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

      <h1>Customer Management</h1>
  
  <?php if (!empty($message)): ?>
    <div class="alert" style="background-color: <?php echo $messageColor === 'green' ? '#e2f0d9' : ($messageColor === 'red' ? '#fce4d6' : '#fff2cc'); ?>; color: <?php echo $messageColor; ?>;">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <div class="grid">
    <div class="form-container">
      <h2>Add New Customer</h2>
      <form action="customer.php" method="POST">
        <label for="customerID">Customer ID</label>
        <input type="text" id="customerID" name="customerID" placeholder="e.g. C001" required>

        <label for="custName">Full Name</label>
        <input type="text" id="custName" name="custName" placeholder="Enter customer name" required>

        <label for="custPhone">Phone Number</label>
        <input type="text" id="custPhone" name="custPhone" placeholder="e.g. 012-3456789">

        <label for="custType">Customer Type</label>
        <select id="custType" name="custType">
          <option value="" disabled selected>Select Type</option>
          <option value="Retail">Retail</option>
          <option value="Wholesale">Wholesale</option>
        </select>

        <label for="custAddress">Delivery Address</label>
        <textarea id="custAddress" name="custAddress" placeholder="Enter full mailing/delivery address"></textarea>

        <button type="submit" name="add_customer">Save Customer Details</button>
      </form>
    </div>

    <div class="table-container">
      <h2>Registered Koti Nursery Customers</h2>
      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Phone</th>
            <th>Type</th>
            <th>Address</th>
            <th style="width:110px">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($customers) > 0): ?>
            <?php foreach ($customers as $cust): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($cust['CUSTOMERID'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars($cust['CUSTNAME'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($cust['CUSTPHONE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($cust['CUSTTYPE'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($cust['CUSTADDRESS'] ?? '-', ENT_QUOTES, 'UTF-8'); ?></td>
                <td>
                  <a href="customer_edit.php?customerID=<?php echo urlencode($cust['CUSTOMERID']); ?>" style="display:inline-block;padding:6px 10px;background:#3f7d4b;color:#fff;border-radius:6px;text-decoration:none;font-size:13px">Edit</a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="5" style="text-align: center; color: #777;">No customer records found in the database.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>