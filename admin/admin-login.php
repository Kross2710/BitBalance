<?php
// Include the initialization file
require_once __DIR__ . '/../include/init.php';
require_once __DIR__ . '/../include/db_config.php';
// Include the user login handler
require_once 'handlers/admin_login.php';
?>

<!-- Modal Structure -->
<div id="loginModal" class="modal">
  <div class="modal-content">
    <button class="close-btn" id="closeLoginModal" aria-label="Close modal">
      &times;
    </button>
    <h2>Admin Login</h2>
    <form action="admin-login.php" method="POST">
      <input type="email" name="email" placeholder="Email" required><br><br>
      <input type="password" name="password" placeholder="Password" required><br><br>
      <button class="login-btn" type="submit">Login</button>
    </form>
  </div>
</div>

<style>
  .modal {
    display: none;
    position: fixed;
    /* Stay in place */
    z-index: 1000;
    /* Sit on top */
    left: 0;
    top: 0;
    width: 100%;
    /* Full width */
    height: 100%;
    /* Full height */
    overflow: auto;
    /* Enable scroll if needed */
    background-color: rgb(0, 0, 0, 0.3);
    /* Fallback color */
  }

  .modal-content {
    position: relative;
    background: white;
    margin: 80px auto;
    padding: 30px 24px;
    border-radius: 5px;
    max-width: 400px;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.16);
    text-align: center;
  }

  .close-btn {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 32px;
    height: 32px;
    border: none;
    color: #888;
    font-size: 24px;
    line-height: 1;
    border-radius: 50%;
    cursor: pointer;
    transition: background 0.2s, color 0.2s;
  }

  .close-btn:hover {
    color: #333;
  }

  .close {
    color: #aaa;
    position: absolute;
    top: 10px;
    right: 18px;
    font-size: 32px;
    font-weight: bold;
    cursor: pointer;
  }

  .close:hover {
    color: #000;
  }

  input {
    width: 100%;
    padding: 10px;
    margin: 10px 0;
    border: 1px solid #ccc;
    border-radius: 4px;
  }

  .login-btn {
    background-color: #4a7ee3;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
  }

  button:hover {
    background-color: #3a6bb3;
  }

  .modal p {
    margin-top: 10px;
  }
</style>

<!-- Modal Script -->
<script>
  const modal = document.getElementById('loginModal');
  const openBtn = document.getElementById('openLoginModal');
  const closeBtn = document.getElementById('closeLoginModal');

  if (openBtn) {
    openBtn.onclick = () => modal.style.display = 'block';
  }

  if (closeBtn) {
    closeBtn.onclick = () => modal.style.display = 'none';
  }
  window.onclick = (e) => {
    if (e.target === modal) modal.style.display = 'none';
  };
</script>