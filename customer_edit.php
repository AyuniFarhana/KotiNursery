<?php
require_once 'db.php';

// Simple edit page: shows form when accessed with GET?customerID=...
// On POST, updates the record and redirects back to customer.php

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $customerID = isset($_GET['customerID']) ? trim($_GET['customerID']) : '';
    if (empty($customerID)) {
        header('Location: customer.php');
        exit;
    }

    $sql = "SELECT customerID, custName, custPhone, custType, custAddress FROM customer WHERE customerID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $customerID);
    oci_execute($stid);
    $row = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS);
    oci_free_statement($stid);

    if (!$row) {
        header('Location: customer.php');
        exit;
    }

    $cust = $row;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_customer'])) {
    $customerID  = trim($_POST['customerID']);
    $custName    = trim($_POST['custName']);
    $custPhone   = trim($_POST['custPhone']);
    $custType    = trim($_POST['custType']);
    $custAddress = trim($_POST['custAddress']);

    if (empty($customerID) || empty($custName)) {
        $message = 'Customer ID and Name are required.';
    } else {
        $update = "UPDATE customer SET custName = :name, custPhone = :phone, custType = :ctype, custAddress = :addr WHERE customerID = :id";
        $stid = oci_parse($conn, $update);
        oci_bind_by_name($stid, ':name', $custName);
        oci_bind_by_name($stid, ':phone', $custPhone);
        oci_bind_by_name($stid, ':ctype', $custType);
        oci_bind_by_name($stid, ':addr', $custAddress);
        oci_bind_by_name($stid, ':id', $customerID);

        $ok = oci_execute($stid, OCI_COMMIT_ON_SUCCESS);
        if ($ok) {
            header('Location: customer.php');
            exit;
        } else {
            $e = oci_error($stid);
            $message = 'Error updating customer: ' . htmlspecialchars($e['message'], ENT_QUOTES, 'UTF-8');
        }
        oci_free_statement($stid);
    }

    // Re-fetch for display
    $sql = "SELECT customerID, custName, custPhone, custType, custAddress FROM customer WHERE customerID = :id";
    $stid = oci_parse($conn, $sql);
    oci_bind_by_name($stid, ':id', $customerID);
    oci_execute($stid);
    $cust = oci_fetch_array($stid, OCI_ASSOC+OCI_RETURN_NULLS);
    oci_free_statement($stid);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Edit Customer</title>
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
            <h1>Edit Customer</h1>
            <p style="margin:12px 0 0;color:var(--muted);max-width:680px;line-height:1.7;">Update customer profile </p>
          </div>
          <a class="return-link" href="customer.php">⬅ Back to Customers</a>
        </div>

        <?php if (!empty($message)): ?>
          <div class="alert"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post" action="customer_edit.php" class="form-grid">
          <div>
            <label for="customerID">Customer ID</label>
            <input type="text" id="customerID" name="customerID" value="<?php echo htmlspecialchars($cust['CUSTOMERID'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" readonly>
          </div>

          <div>
            <label for="custName">Full Name</label>
            <input type="text" id="custName" name="custName" value="<?php echo htmlspecialchars($cust['CUSTNAME'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" required>
          </div>

          <div>
            <label for="custPhone">Phone</label>
            <input type="text" id="custPhone" name="custPhone" value="<?php echo htmlspecialchars($cust['CUSTPHONE'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
          </div>

          <div>
            <label for="custType">Type</label>
            <select id="custType" name="custType">
              <option value="" <?php echo empty($cust['CUSTTYPE']) ? 'selected' : ''; ?>>Select Type</option>
              <option value="Retail" <?php echo (isset($cust['CUSTTYPE']) && $cust['CUSTTYPE']==='Retail') ? 'selected' : ''; ?>>Retail</option>
              <option value="Wholesale" <?php echo (isset($cust['CUSTTYPE']) && $cust['CUSTTYPE']==='Wholesale') ? 'selected' : ''; ?>>Wholesale</option>
            </select>
          </div>

          <div style="grid-column:1/-1;">
            <label for="custAddress">Address</label>
            <textarea id="custAddress" name="custAddress"><?php echo htmlspecialchars($cust['CUSTADDRESS'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
          </div>

          <button type="submit" name="save_customer">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</body>
</html>
