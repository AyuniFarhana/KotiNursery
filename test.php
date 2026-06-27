<?php
// Figure out which page is currently active, to highlight it in the sidebar
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
  .sidebar {
    width: 200px;
    flex-shrink: 0;
    background: #1f3a26;
    color: white;
    min-height: 100vh;
    padding: 20px 0;
  }
  .sidebar .brand {
    font-size: 16px;
    font-weight: bold;
    padding: 0 18px 16px;
    border-bottom: 1px solid #2e4d36;
    margin-bottom: 12px;
  }
  .sidebar .nav-item {
    display: block;
    padding: 12px 18px;
    color: #c9d6c9;
    text-decoration: none;
    font-size: 14px;
  }
  .sidebar .nav-item:hover {
    background: #25422c;
    color: white;
  }
  .sidebar .nav-item.active {
    background: #2e4d36;
    color: white;
    font-weight: bold;
    border-left: 4px solid #6fbf73;
  }
  .sidebar .logout {
    display: block;
    margin: 24px 18px 0;
    padding: 10px 14px;
    background: #a13636;
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 13px;
    text-align: center;
  }
  .sidebar .logout:hover {
    background: #842b2b;
  }
  .app-layout {
    display: flex;
  }
  .main-content {
    flex: 1;
    padding: 30px;
    background: #f4f8f2;
    min-height: 100vh;
  }
</style>

<div class="sidebar">
  <div class="brand">🌿 Koti Nursery</div>

  <a href="dashboard.php" class="nav-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
    Dashboard
  </a>
  <a href="supplier.php" class="nav-item <?php echo $currentPage === 'supplier.php' ? 'active' : ''; ?>">
    Suppliers
  </a>
  <a href="product.php" class="nav-item <?php echo $currentPage === 'product.php' ? 'active' : ''; ?>">
    Products
  </a>
  <a href="customer.php" class="nav-item <?php echo $currentPage === 'customer.php' ? 'active' : ''; ?>">
    Customers
  </a>
  <a href="customerorder.php" class="nav-item <?php echo $currentPage === 'customerorder.php' ? 'active' : ''; ?>">
    Orders
  </a>
  <a href="employee.php" class="nav-item <?php echo $currentPage === 'employee.php' ? 'active' : ''; ?>">
    Employees
  </a>

  <a href="logout.php" class="logout">Logout</a>
</div>