<?php
require_once 'db.php';

function fetchOracleCount($conn, array $tableCandidates)
{
    foreach ($tableCandidates as $table) {
        $sql = "SELECT COUNT(*) AS CNT FROM " . $table;
        $stmt = oci_parse($conn, $sql);
        if (!$stmt) {
            continue;
        }

        if (!oci_execute($stmt)) {
            $error = oci_error($stmt);
            oci_free_statement($stmt);
            if (isset($error['message']) && strpos($error['message'], 'ORA-00942') !== false) {
                continue;
            }
            continue;
        }

        $row = oci_fetch_assoc($stmt);
        oci_free_statement($stmt);

        if ($row && isset($row['CNT'])) {
            return (int) $row['CNT'];
        }
    }

    return 0;
}

$totals = [
    'customers' => fetchOracleCount($conn, ['CUSTOMER', 'CUSTOMERS', 'CUSTOMER_TBL']),
    'employees' => fetchOracleCount($conn, ['EMPLOYEE', 'EMPLOYEES']),
    'orders' => fetchOracleCount($conn, ['CUSTOMERORDER', 'CUSTOMER_ORDER', 'CUSTOMER_ORDERS', 'ORDERDETAIL', 'ORDERS', 'ORDER']),
    'products' => fetchOracleCount($conn, ['PRODUCT', 'PRODUCTS']),
    'suppliers' => fetchOracleCount($conn, ['SUPPLIER', 'SUPPLIERS']),
  ];

  // Build product vs supplier chart data by trying a few common table/column patterns.
  function fetchProductSupplierData($conn)
  {
    $productTables = ['PRODUCT', 'PRODUCTS', 'PRODUCT_TBL'];
    $supplierTables = ['SUPPLIER', 'SUPPLIERS', 'VENDOR', 'VENDORS'];

    $try = function ($sql) use ($conn) {
      $stmt = @oci_parse($conn, $sql);
      if (!$stmt) return false;
      if (!@oci_execute($stmt)) return false;
      $rows = [];
      while (($r = oci_fetch_assoc($stmt)) !== false) {
        $rows[] = $r;
      }
      oci_free_statement($stmt);
      return $rows;
    };

    // 1) Try grouping by a supplier column on the product table (p.SUPPLIER)
    foreach ($productTables as $pt) {
      $sql = "SELECT p.SUPPLIER AS NAME, COUNT(*) AS CNT FROM " . $pt . " p GROUP BY p.SUPPLIER ORDER BY CNT DESC";
      $rows = $try($sql);
      if ($rows && count($rows) > 0) return $rows;
    }

    // 2) Try grouping by supplier id on product table
    foreach ($productTables as $pt) {
      $sql = "SELECT p.SUPPLIER_ID AS NAME, COUNT(*) AS CNT FROM " . $pt . " p GROUP BY p.SUPPLIER_ID ORDER BY CNT DESC";
      $rows = $try($sql);
      if ($rows && count($rows) > 0) return $rows;
    }

    // 3) Try joining product -> supplier using common column names
    foreach ($productTables as $pt) {
      foreach ($supplierTables as $st) {
        $sqls = [
          "SELECT s.SUPPLIER_NAME AS NAME, COUNT(*) AS CNT FROM {$pt} p JOIN {$st} s ON p.SUPPLIER_ID = s.SUPPLIER_ID GROUP BY s.SUPPLIER_NAME ORDER BY CNT DESC",
          "SELECT s.NAME AS NAME, COUNT(*) AS CNT FROM {$pt} p JOIN {$st} s ON p.SUPPLIER_ID = s.ID GROUP BY s.NAME ORDER BY CNT DESC",
          "SELECT s.SUPPLIER AS NAME, COUNT(*) AS CNT FROM {$pt} p JOIN {$st} s ON p.SUPPLIER = s.SUPPLIER GROUP BY s.SUPPLIER ORDER BY CNT DESC",
        ];
        foreach ($sqls as $sql) {
          $rows = $try($sql);
          if ($rows && count($rows) > 0) return $rows;
        }
      }
    }

    return [];
  }

  $psRows = fetchProductSupplierData($conn);
  $chartData = ['labels' => [], 'values' => []];
  foreach ($psRows as $r) {
    $name = '';
    if (isset($r['NAME'])) $name = $r['NAME'];
    elseif (isset($r['SUPPLIER_NAME'])) $name = $r['SUPPLIER_NAME'];
    elseif (isset($r['SUPPLIER'])) $name = $r['SUPPLIER'];
    elseif (isset($r['SUPPLIER_ID'])) $name = $r['SUPPLIER_ID'];
    else $name = '(unknown)';
    $chartData['labels'][] = $name;
    $chartData['values'][] = isset($r['CNT']) ? (int)$r['CNT'] : 0;
  }
  $chartJson = json_encode($chartData);
  
  // Inventory breakdown (pie/donut) - try grouping by category/type columns
  function fetchInventoryBreakdown($conn)
  {
    $productTables = ['PRODUCT', 'PRODUCTS', 'PRODUCT_TBL'];
    $groupCols = ['CATEGORY', 'CATEGORY_NAME', 'TYPE', 'PRODUCT_TYPE', 'CAT'];

    $try = function ($sql) use ($conn) {
      $stmt = @oci_parse($conn, $sql);
      if (!$stmt) return false;
      if (!@oci_execute($stmt)) return false;
      $rows = [];
      while (($r = oci_fetch_assoc($stmt)) !== false) $rows[] = $r;
      oci_free_statement($stmt);
      return $rows;
    };

    foreach ($productTables as $pt) {
      foreach ($groupCols as $col) {
        $sql = "SELECT p." . $col . " AS NAME, COUNT(*) AS CNT FROM " . $pt . " p GROUP BY p." . $col . " ORDER BY CNT DESC";
        $rows = $try($sql);
        if ($rows && count($rows) > 0) return $rows;
      }
    }

    return [];
  }

  // Low stock alerts - find products with small quantities
  function fetchLowStockItems($conn, $threshold = 5, $limit = 10)
  {
    $productTables = ['PRODUCT', 'PRODUCTS', 'PRODUCT_TBL'];
    $nameCols = ['PRODUCT_NAME', 'NAME', 'PROD_NAME', 'PNAME', 'DESCRIPTION'];
    $qtyCols = ['QTY', 'QUANTITY', 'STOCK', 'QOH', 'ON_HAND', 'AVAILABLE'];

    $try = function ($sql) use ($conn) {
      $stmt = @oci_parse($conn, $sql);
      if (!$stmt) return false;
      if (!@oci_execute($stmt)) return false;
      $rows = [];
      while (($r = oci_fetch_assoc($stmt)) !== false) $rows[] = $r;
      oci_free_statement($stmt);
      return $rows;
    };

    foreach ($productTables as $pt) {
      foreach ($qtyCols as $qc) {
        foreach ($nameCols as $nc) {
          $sql = "SELECT p." . $nc . " AS NAME, p." . $qc . " AS QTY FROM " . $pt . " p WHERE p." . $qc . " IS NOT NULL AND p." . $qc . " <= " . (int)$threshold . " ORDER BY p." . $qc . " ASC";
          $rows = $try($sql);
          if ($rows && count($rows) > 0) return $rows;
        }
      }
    }

    return [];
  }

  $inventoryRows = fetchInventoryBreakdown($conn);
  $invData = ['labels' => [], 'values' => []];
  foreach ($inventoryRows as $r) {
    $label = isset($r['NAME']) ? $r['NAME'] : '(unknown)';
    $invData['labels'][] = $label;
    $invData['values'][] = isset($r['CNT']) ? (int)$r['CNT'] : 0;
  }
  $inventoryJson = json_encode($invData);

  $lowRows = fetchLowStockItems($conn, 5, 10);
  $lowData = ['labels' => [], 'values' => [], 'items' => []];
  foreach ($lowRows as $r) {
    $name = isset($r['NAME']) ? $r['NAME'] : '(unknown)';
    $qty = isset($r['QTY']) ? (int)$r['QTY'] : 0;
    $lowData['labels'][] = $name;
    $lowData['values'][] = $qty;
    $lowData['items'][] = ['name' => $name, 'qty' => $qty];
  }
  $lowStockJson = json_encode($lowData);
  ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Koti Nursery - Dashboard</title>
  <style>
    :root {
      color-scheme: light;
      --bg: #eef3ea;
      --surface: #ffffff;
      --surface-soft: #f4fbf4;
      --text: #203627;
      --muted: #5c6d57;
      --accent: #3f7d4b;
      --accent-dark: #2f5f38;
      --border: rgba(62, 99, 57, 0.16);
    }

    * {
      box-sizing: border-box;
    }

    body {
      margin: 0;
      min-height: 100vh;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: radial-gradient(circle at top left, rgba(63, 125, 75, 0.14), transparent 28%),
                  linear-gradient(180deg, #fbfdfa 0%, var(--bg) 100%);
      color: var(--text);
    }

    .container {
      width: min(1160px, calc(100% - 32px));
      margin: 0 auto;
      padding: 32px 0 48px;
    }

    .header {
      display: grid;
      gap: 18px;
      padding: 32px;
      border-radius: 28px;
      background: rgba(255,255,255,0.94);
      border: 1px solid var(--border);
      box-shadow: 0 18px 38px rgba(33, 69, 35, 0.08);
    }

    .header h1 {
      margin: 0;
      font-size: clamp(2rem, 2.7vw, 3.4rem);
      letter-spacing: -0.03em;
    }

    .header p {
      margin: 0;
      max-width: 760px;
      line-height: 1.8;
      color: var(--muted);
    }

    .row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 16px;
      margin-top: 18px;
    }

    .stat {
      padding: 20px 22px;
      border-radius: 18px;
      background: var(--surface-soft);
      border: 1px solid var(--border);
      color: var(--text);
    }

    .stat strong {
      display: block;
      font-size: 1.6rem;
      margin-bottom: 8px;
    }

    .grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 22px;
      margin: 36px 0 0;
    }

    .card {
      position: relative;
      padding: 28px 24px;
      border-radius: 24px;
      background: linear-gradient(180deg, rgba(255,255,255,0.96), rgba(242,249,237,0.96));
      border: 1px solid rgba(59, 99, 59, 0.12);
      box-shadow: 0 14px 34px rgba(25, 62, 28, 0.08);
      transition: transform 0.25s ease, box-shadow 0.25s ease;
      overflow: hidden;
    }

    .card:hover {
      transform: translateY(-4px);
      box-shadow: 0 22px 44px rgba(25, 62, 28, 0.14);
    }

    .card::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at top right, rgba(63, 125, 75, 0.14), transparent 30%);
      pointer-events: none;
    }

    .card a {
      position: relative;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      text-decoration: none;
      color: var(--accent);
      font-weight: 700;
      font-size: 1.1rem;
      z-index: 1;
    }

    .card p {
      margin: 14px 0 0;
      color: var(--muted);
      line-height: 1.75;
      z-index: 1;
    }

    .logout {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 14px 28px;
      border-radius: 999px;
      background: var(--accent);
      color: #fff;
      text-decoration: none;
      font-weight: 600;
      transition: background 0.2s ease;
    }

    .logout:hover {
      background: var(--accent-dark);
    }

    .main-content { padding: 24px 0 48px; }

    @media (max-width: 640px) {
      .container { padding: 24px 0 32px; }
      .header { padding: 24px; }
      .card { padding: 22px 18px; }
    }
  </style>
</head>
<body>
  <main class="main-content">
    <div class="container">
        <section class="header">
      <div>
        <h1>Koti Nursery Dashboard</h1>
        <p>Welcome back!</p>
      </div>
      <div class="row">
        <div class="stat">
          <strong><?php echo htmlspecialchars($totals['customers'], ENT_QUOTES, 'UTF-8'); ?></strong>
          Total Customers
        </div>
        <div class="stat">
          <strong><?php echo htmlspecialchars($totals['employees'], ENT_QUOTES, 'UTF-8'); ?></strong>
          Total Employees
        </div>
        <div class="stat">
          <strong><?php echo htmlspecialchars($totals['orders'], ENT_QUOTES, 'UTF-8'); ?></strong>
          Total Orders
        </div>
        <div class="stat">
          <strong><?php echo htmlspecialchars($totals['products'], ENT_QUOTES, 'UTF-8'); ?></strong>
          Total Products
        </div>
        <div class="stat">
          <strong><?php echo htmlspecialchars($totals['suppliers'], ENT_QUOTES, 'UTF-8'); ?></strong>
          Total Suppliers
        </div>
      </div>
    </section>

    <div class="grid" style="margin-top: 24px;">
      <div class="card">
        <a href="customer.php">Customers</a>
        <p>View and manage customer records.</p>
      </div>
      <div class="card">
        <a href="order.php">Orders</a>
        <p>View current and past customer orders.</p>
      </div>
      <div class="card">
        <a href="product.php">Products</a>
        <p>Manage product inventory and pricing.</p>
      </div>
      <div class="card">
        <a href="supplier.php">Suppliers</a>
        <p>Manage supplier information and stock sources.</p>
      </div>
    </div>

    <section class="chart-section" style="margin-top:28px;">
      <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start">
        <div>
          <h2 style="margin:0 0 12px;font-size:1.05rem;color:var(--muted)">Products per Supplier</h2>
          <div style="background:var(--surface);padding:18px;border-radius:12px;border:1px solid var(--border);box-shadow:0 10px 20px rgba(20,40,20,0.04);height:260px;">
            <canvas id="prodSupplierChart" style="width:100%;height:100%"></canvas>
          </div>

          <h3 style="margin:14px 0 8px;font-size:0.98rem;color:var(--muted)">Inventory Breakdown</h3>
          <div style="background:var(--surface);padding:12px;border-radius:12px;border:1px solid var(--border);box-shadow:0 6px 12px rgba(20,40,20,0.03);height:200px;display:flex;align-items:center;justify-content:center;">
            <canvas id="inventoryDonut" style="max-width:320px;max-height:160px;width:100%;height:100%"></canvas>
          </div>
        </div>

        <aside>
          <h2 style="margin:0 0 12px;font-size:1.05rem;color:var(--muted)">Low Stock Alerts</h2>
          <div style="background:var(--surface);padding:12px;border-radius:12px;border:1px solid var(--border);box-shadow:0 6px 12px rgba(20,40,20,0.03)">
            <div style="height:140px;margin-bottom:12px;">
              <canvas id="lowStockChart" style="width:100%;height:100%"></canvas>
            </div>
            <div id="lowStockList" style="max-height:200px;overflow:auto"></div>
          </div>
        </aside>
      </div>
    </section>

    <div style="margin-top:18px;">
       <br>
       <a class="logout" href="index.php">Logout</a>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
      (function(){
        var psData = <?php echo $chartJson ?: json_encode(['labels'=>[], 'values'=>[]]); ?>;
        var invData = <?php echo $inventoryJson ?: json_encode(['labels'=>[], 'values'=>[]]); ?>;
        var lowData = <?php echo $lowStockJson ?: json_encode(['labels'=>[], 'values'=>[], 'items'=>[]]); ?>;

        function palette(n){
          var base = [[63,125,75],[94,158,95],[133,185,128],[190,222,181],[95,155,93],[60,120,70]];
          var out = [];
          for(var i=0;i<n;i++){ var c = base[i % base.length]; var a = 0.75; out.push('rgba('+c.join(',')+','+a+')'); }
          return out;
        }

        // Products per supplier (bar)
        (function(){
          var ctx = document.getElementById('prodSupplierChart').getContext('2d');
          new Chart(ctx, {
            type: 'bar',
            data: { labels: psData.labels, datasets: [{ data: psData.values, backgroundColor: 'rgba(63,125,75,0.7)', borderColor:'rgba(47,93,55,0.9)', borderWidth:1 }] },
            options: { responsive:true, maintainAspectRatio:false, scales:{ y:{ beginAtZero:true, ticks:{ precision:0 } } }, plugins:{ legend:{ display:false } } }
          });
        })();

        // Inventory donut
        (function(){
          var ctx = document.getElementById('inventoryDonut').getContext('2d');
          var colors = palette(invData.labels.length);
          new Chart(ctx, { type:'doughnut', data: { labels: invData.labels, datasets:[{ data: invData.values, backgroundColor: colors }] }, options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ position:'right' } } } });
        })();

        // Low stock bar and list
        (function(){
          var ctx = document.getElementById('lowStockChart').getContext('2d');
          var labels = lowData.labels.slice().reverse();
          var values = lowData.values.slice().reverse();
          new Chart(ctx, { type:'bar', data:{ labels: labels, datasets:[{ data: values, backgroundColor:'rgba(220,80,80,0.8)' }] }, options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ beginAtZero:true } } } });

          var list = document.getElementById('lowStockList');
          if(lowData.items && lowData.items.length){
            lowData.items.forEach(function(it){
              var pct = Math.max(0, Math.min(100, Math.round((it.qty/5)*100)));
              var wrapper = document.createElement('div');
              wrapper.style.marginBottom = '8px';
              wrapper.innerHTML = '<div style="font-weight:600;color:var(--text)">' + (it.name || '(unknown)') + ' <span style="float:right;color:var(--muted)">' + it.qty + '</span></div>' +
                                  '<div style="background:#eee;border-radius:6px;overflow:hidden;height:10px;margin-top:6px">' +
                                  '<div style="width:'+pct+'%;height:100%;background:linear-gradient(90deg, #ffb3b3, #ff6b6b);"></div></div>';
              list.appendChild(wrapper);
            });
          } else {
            list.innerHTML = '<div style="color:var(--muted)">No low-stock items detected.</div>';
          }
        })();
      })();
    </script>
      </div>
    </main>
  </div>
</body>
</html>
