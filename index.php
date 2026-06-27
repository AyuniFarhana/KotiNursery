<?php
session_start();
include("db.php");

$error = "";

if(isset($_POST['login']))
{
    $empID = trim($_POST['empID']);
    $password1 = trim($_POST['password1']);

    $sql = "SELECT EMPID, EMPNAME, EMPROLE
            FROM EMPLOYEE
            WHERE TRIM(EMPID) = :empID
            AND TRIM(PASSWORD1) = :password1";

    $stmt = oci_parse($conn, $sql);

    oci_bind_by_name($stmt, ":empID", $empID);
    oci_bind_by_name($stmt, ":password1", $password1);

    $result = oci_execute($stmt);

    if(!$result)
    {
        $e = oci_error($stmt);
        die("SQL Error: " . $e['message']);
    }

    $row = oci_fetch_assoc($stmt);

    if($row)
    {
        $_SESSION['empID'] = $row['EMPID'];
        $_SESSION['empName'] = $row['EMPNAME'];
        $_SESSION['empRole'] = $row['EMPROLE'];

        header("Location: dashboard.php");
        exit();
    }
    else
    {
        $error = "Invalid Employee ID or Password";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Koti Nursery Login</title>
<style>
  * {
    box-sizing: border-box;
  }

  body {
    margin: 0;
    min-height: 100vh;
    font-family: 'Inter', Arial, sans-serif;
    background-image: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.25)), url('gb1.jpg');
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    background-attachment: fixed;
    color: #2e4232;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 24px;
}

  .login-page {
    width: 100%;
    max-width: 440px;
  }

  .login-card {
    background: rgba(255, 255, 255, 0.96);
    border: 1px solid rgba(46, 92, 52, 0.12);
    border-radius: 24px;
    padding: 36px;
    box-shadow: 0 18px 50px rgba(53, 80, 54, 0.12);
    transition: transform 200ms ease, box-shadow 200ms ease;
  }

  .login-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 60px rgba(53, 80, 54, 0.16);
  }

  .brand {
    text-align: center;
    margin-bottom: 24px;
  }

  .brand h1 {
    margin: 0;
    font-size: 2rem;
    letter-spacing: -0.04em;
    color: #2b5b33;
  }

  .subtitle {
    margin: 8px auto 0;
    color: #556d56;
    font-size: 0.95rem;
  }

  form {
    display: grid;
    gap: 18px;
  }

  label {
    display: block;
    font-weight: 700;
    color: #3c5a41;
    margin-bottom: 6px;
  }

  input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid #cad4c7;
    border-radius: 14px;
    background: #fbfcfb;
    font-size: 1rem;
    color: #2e4232;
    transition: border-color 150ms ease, box-shadow 150ms ease;
  }

  input:focus {
    outline: none;
    border-color: #80b17d;
    box-shadow: 0 0 0 4px rgba(128, 177, 125, 0.12);
  }

  button {
    width: 100%;
    padding: 14px 16px;
    border: none;
    border-radius: 14px;
    background: linear-gradient(135deg, #3b7a44 0%, #2d5b31 100%);
    color: #fff;
    font-size: 1rem;
    font-weight: 700;
    cursor: pointer;
    transition: transform 120ms ease, box-shadow 120ms ease, opacity 120ms ease;
  }

  button:hover {
    transform: translateY(-1px);
    box-shadow: 0 10px 22px rgba(45, 91, 49, 0.2);
  }

  button:active {
    transform: translateY(0);
  }

  .note {
    margin: 0;
    color: #5c6f58;
    font-size: 0.94rem;
  }

  .error {
    margin: 0;
    padding: 12px 16px;
    border-radius: 14px;
    background: #fce7e7;
    color: #9d3232;
    border: 1px solid #f5c0c0;
    font-size: 0.95rem;
  }

  @media (max-width: 500px) {
    .login-card {
      padding: 28px 22px;
    }

    body {
      padding: 16px;
    }
  }
</style>
</head>
<body>
  <div class="login-page">
    <div class="login-card">
      <div class="brand">
        <h1>🌿 Koti Nursery</h1>
        <p class="subtitle">Welcome Back To Koti Nursery Management System</p>
      </div>

      <form method="POST">

    <label>Employee ID</label>
    <input type="text" name="empID" placeholder="Enter your employee ID" required>

    <label>Password</label>
    <input type="password" name="password1" placeholder="Enter your password" required>

    <button type="submit" name="login">Login</button>

    <?php
    if(!empty($error))
    {
        echo "<p class='error'>$error</p>";
    }
    ?>

  </form>
    </div>
  </div>

</body>
</html>