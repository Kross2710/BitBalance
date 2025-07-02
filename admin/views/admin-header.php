    <header>
        <a href="admin.php" class="logo">BitBalance <span>Administrator</span></a>
        <nav>
        <div class="menu">

            <?php if (isset($_SESSION['user'])): // Check if user is logged in ?>
            <a href="#" class="login-signup">Hi, <?= htmlspecialchars($_SESSION['user']['first_name']) ?></a>
            <a href="admin-logout.php" class="login-signup">Logout</a>
            <?php else: ?>
                <a href="javascript:void(0);"
                    id="openLoginModal"
                   class="login-signup">Sign In</a>
            <?php endif; ?>

        </div>
    
            <!-- Hamberger menu to toggle nav links -->
            <a href="javascript:void(0);" class="hamburger" onclick="toggleMenu()">
                <i class="fa-solid fa-bars"></i>
            </a>
            
        </nav>
    </header>
    <style>
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 30px;
        }

        span {
            color: red;
        }

        .logo {
            text-decoration: none;
            color: black;
            font-weight: bold;
            font-size: 20px;
        }

        nav {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .menu a {
            text-decoration: none;
            color: black;
            transition: all 0.3s ease;
        }

        .menu a.active {
            color: #4a7ee3;
            font-weight: bold;
        }

        .menu a:hover {
            color: #4a7ee3;
            font-weight: bold;
            transition: all 0.3s ease;
        }

        .icon .fa-cart-shopping {
            font-size: 22px;
        }

        .login-signup {



        }

        .shopping-cart {


        }

        .menu .login-signup:hover {
            background-color: #4a7ee3;
            color: white;
            transition: background-color 0.3s ease;
            border: 1px solid #ccc;
        }

        .menu .shopping-cart:hover {
            background-color: #4a7ee3;
            color: white;
            transition: background-color 0.3s ease;
            border: 1px solid #ccc;
        }

        nav {
            position: relative;
            overflow: hidden;
        }

        nav .menu {
            overflow: hidden;
            transition: max-height 0.4s ease-out;
        }

        nav .menu.show {
            max-height: 500px; /* Adjust as needed */
        }

        nav .menu a {
            display: block;
            padding: 12px 16px;
            text-decoration: none;
            color: black;
            border-bottom: 1px solid #ccc;
        }

        /* Small screen layout */
        @media (max-width: 768px) {
            nav .menu {
            max-height: 0;
        }
        }

        /* Large screen layout */
        @media (min-width: 768px) {
            nav a.hamburger {
                display: none;
            }

            nav .menu {
                display: flex;
            }

            nav .menu a {
                display: inline-block;
            }
        }
    </style>
    <script>
        function toggleMenu() {
            document.querySelector('.menu').classList.toggle('show');
        }
    </script>