<?php
// Include the database connection file
require_once 'db.php';

$message = "";
$messageColor = "green";

// Handle Form Submission (Create New Order)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['create_order'])) {
    $orderID        = trim($_POST['orderID']);
    $custID         = $_POST['custID'];
    $empID          = $_POST['empID'];
    $deliveryMethod = $_POST['deliveryMethod'];
    $productId      = $_POST['productId'];
    $orderQty       = intval($_POST['orderQty']);

    if (!empty($orderID) && !empty($custID) && !empty($empID) && !empty($productId) && $orderQty > 0) {
        
        // 1. Fetch the product's unit price to calculate the subtotal
        $priceQuery = "SELECT unitPrice, stockQty FROM product WHERE productId = :prod_id";
        $priceStid = oci_parse($conn, $priceQuery);
        oci_bind_by_name($priceStid, ":prod_id", $productId);
        oci_execute($priceStid);
        $productRow = oci_fetch_array($priceStid, OCI_ASSOC);
        oci_free_statement($priceStid);

        if ($productRow) {
            $unitPrice = floatval($productRow['UNITPRICE']);
            $currentStock = intval($productRow['STOCKQTY']);
            
            // Check if stock is sufficient
            if ($orderQty > $currentStock) {
                $message = "⚠️ Insufficient stock! Only $currentStock units left for this product.";
                $messageColor = "orange";
            } else {
                $subtotal = $unitPrice * $orderQty;
                $totalAmount = $subtotal; // For a single-item order setup

                // 2. Insert into customerorder (Parent)
                $orderQuery = "INSERT INTO customerorder (orderID, orderDate, deliveryMethod, totalAmount, custID, empID) 
                               VALUES (:order_id, SYSDATE, :delivery, :total, :cust_id, :emp_id)";
                $orderStid = oci_parse($conn, $orderQuery);
                oci_bind_by_name($orderStid, ":order_id", $orderID);
                oci_bind_by_name($orderStid, ":delivery", $deliveryMethod);
                oci_bind_by_name($orderStid, ":total", $totalAmount);
                oci_bind_by_name($orderStid, ":cust_id", $custID);
                oci_bind_by_name($orderStid, ":emp_id", $empID);

                $orderSuccess = oci_execute($orderStid, OCI_NO_AUTO_COMMIT);

                if ($orderSuccess) {
                    // 3. Insert into orderdetail (Child)
                    $detailQuery = "INSERT INTO orderdetail (orderId, productId, orderQty, subtotal) 
                                    VALUES (:order_id, :prod_id, :qty, :subtotal)";
                    $detailStid = oci_parse($conn, $detailQuery);
                    oci_bind_by_name($detailStid, ":order_id", $orderID);
                    oci_bind_by_name($detailStid, ":prod_id", $productId);
                    oci_bind_by_name($detailStid, ":qty", $orderQty);
                    oci_bind_by_name($detailStid, ":subtotal", $subtotal);

                    $detailSuccess = oci_execute($detailStid, OCI_NO_AUTO_COMMIT);

                    if ($detailSuccess) {
                        // 4. Deduct Stock from Product Table
                        $updateStockQuery = "UPDATE product SET stockQty = stockQty - :qty WHERE productId = :prod_id";
                        $stockStid = oci_parse($conn, $updateStockQuery);
                        oci_bind_by_name($stockStid, ":qty", $orderQty);
                        oci_bind_by_name($stockStid, ":prod_id", $productId);
                        $stockSuccess = oci_execute($stockStid, OCI_NO_AUTO_COMMIT);
                        oci_free_statement($stockStid);

                        if ($stockSuccess) {
                            oci_commit($conn); // Save all modifications permanently
                            $message = "✅ Order '$orderID' placed successfully! Stock updated.";
                            $messageColor = "green";
                        } else {
                            oci_rollback($conn);
                            $message = "❌ Failed to update inventory levels. Transaction rolled back.";
                            $messageColor = "red";
                        }
                    } else {
                        oci_rollback($conn);
                        $message = "❌ Error writing item itemization lines. Transaction rolled back.";
                        $messageColor = "red";
                    }
                    oci_free_statement($detailStid);
                } else {
                    $e = oci_error($orderStid);
                    oci_rollback($conn);
                    if (strpos($e['message'], 'ORA-00001') !== false) {
                        $message = "⚠️ Order ID '$orderID' already exists in the system!";
                        $messageColor = "orange";
                    } else {
                        $message = "❌ Error processing order header: " . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
                        $messageColor = "red";
                    }
                }
                oci_free_statement($orderStid);
            }
        } else {
            $message = "❌ Target product could not be identified inside the inventory context.";
            $messageColor = "red";
        }
    } else {
        $message = "⚠️ All parameters are strictly required to create a invoice transaction.";
        $messageColor = "orange";
    }
}

// Fetch Customers Dropdown Data
$custStid = oci_parse($conn, "SELECT customerID, custName FROM customer ORDER BY custName ASC");
oci_execute($custStid);
$customers = [];
while ($r = oci_fetch_array($custStid, OCI_ASSOC)) { $customers[] = $r; }
oci_free_statement($custStid);

// Fetch Employees Dropdown Data
$empStid = oci_parse($conn, "SELECT empID, empName FROM employee ORDER BY empName ASC");
oci_execute($empStid);
$employees = [];
while ($r = oci_fetch_array($empStid, OCI_ASSOC)) { $employees[] = $r; }
oci_free_statement($empStid);

// Fetch Products Dropdown Data
$prodStid = oci_parse($conn, "SELECT productId, productName, unitPrice FROM product ORDER BY productName ASC");
oci_execute($prodStid);
$productsList = [];
while ($r = oci_fetch_array($prodStid, OCI_ASSOC)) { $productsList[] = $r; }
oci_free_statement($prodStid);

// Fetch Placed Orders to Display in Table Layout
$viewQuery = "SELECT o.orderID, c.custName, e.empName, p.productName, d.orderQty, o.totalAmount, o.deliveryMethod, o.orderDate
              FROM customerorder o
              JOIN customer c ON o.custID = c.customerID
              JOIN employee e ON o.empID = e.empID
              JOIN orderdetail d ON o.orderID = d.orderId
              JOIN product p ON d.productId = p.productId
              ORDER BY o.orderDate DESC";
$viewStid = oci_parse($conn, $viewQuery);
oci_execute($viewStid);
$ordersGrid = [];
while ($row = oci_fetch_array($viewStid, OCI_ASSOC)) { $ordersGrid[] = $row; }
oci_free_statement($viewStid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Koti Nursery — Order Management</title>
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
  </style>
  <?php include 'sidebar_styles.php'; ?>
</head>
<body>
  <div class="page-shell">
    <?php include 'sidebar.php'; ?>
    <main class="main-content">

      <h1>Order Checkout Processing</h1>
  
  <?php if (!empty($message)): ?>
    <div class="alert" style="background-color: <?php echo $messageColor === 'green' ? '#e2f0d9' : ($messageColor === 'red' ? '#fce4d6' : '#fff2cc'); ?>; color: <?php echo $messageColor; ?>;">
      <?php echo $message; ?>
    </div>
  <?php endif; ?>

  <div class="grid">
    <div class="form-container">
      <h2>Create New Order</h2>
      <form action="order.php" method="POST">
        <label for="orderID">Order ID</label>
        <input type="text" id="orderID" name="orderID" placeholder="e.g., ORD1002" required>

        <label for="custID">Customer</label>
        <select id="custID" name="custID" required>
          <option value="" disabled selected>Select Customer</option>
          <?php foreach ($customers as $c): ?>
            <option value="<?php echo htmlspecialchars($c['CUSTOMERID'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($c['CUSTNAME'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="empID">Assigned Employee (Intern/Manager)</label>
        <select id="empID" name="empID" required>
          <option value="" disabled selected>Select Employee</option>
          <?php foreach ($employees as $e): ?>
            <option value="<?php echo htmlspecialchars($e['EMPID'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($e['EMPNAME'], ENT_QUOTES, 'UTF-8'); ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="productId">Select Product Item</label>
        <select id="productId" name="productId" required>
          <option value="" disabled selected>Select Stock Item</option>
          <?php foreach ($productsList as $p): ?>
            <option value="<?php echo htmlspecialchars($p['PRODUCTID'], ENT_QUOTES, 'UTF-8'); ?>">
              <?php echo htmlspecialchars($p['PRODUCTNAME'], ENT_QUOTES, 'UTF-8'); ?> (RM <?php echo number_format($p['UNITPRICE'], 2); ?>)
            </option>
          <?php endforeach; ?>
        </select>

        <label for="orderQty">Order Quantity</label>
        <input type="number" id="orderQty" name="orderQty" min="1" value="1" required>

        <label for="deliveryMethod">Fulfillment Route</label>
        <select id="deliveryMethod" name="deliveryMethod" required>
          <option value="" disabled selected>Select Method</option>
          <option value="Walk-in">Walk-in (Self-Pickup)</option>
          <option value="Lalamove">Lalamove Delivery</option>
          <option value="Postage">Standard Postage</option>
        </select>

        <button type="submit" name="create_order">Process Order Check</button>
      </form>
    </div>

    <div class="table-container">
      <h2>Checkout Transaction Logs</h2>
      <table>
        <thead>
          <tr>
            <th>Order ID</th>
            <th>Customer</th>
            <th>Handled By</th>
            <th>Product Name</th>
            <th>Qty</th>
            <th>Total Amount</th>
            <th>Method</th>
          </tr>
        </thead>
        <tbody>
          <?php if (count($ordersGrid) > 0): ?>
            <?php foreach ($ordersGrid as $ord): ?>
              <tr>
                <td><strong><?php echo htmlspecialchars($ord['ORDERID'], ENT_QUOTES, 'UTF-8'); ?></strong></td>
                <td><?php echo htmlspecialchars($ord['CUSTNAME'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($ord['EMPNAME'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($ord['PRODUCTNAME'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><?php echo htmlspecialchars($ord['ORDERQTY'], ENT_QUOTES, 'UTF-8'); ?></td>
                <td><strong>RM <?php echo number_format($ord['TOTALAMOUNT'], 2); ?></strong></td>
                <td><?php echo htmlspecialchars($ord['DELIVERYMETHOD'], ENT_QUOTES, 'UTF-8'); ?></td>
              </tr>
            <?php endforeach; ?>
          <?php else: ?>
            <tr>
              <td colspan="7" style="text-align: center; color: #777;">No active business orders currently tracked in database context.</td>
            </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</body>
</html>