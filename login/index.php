<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://kit.fontawesome.com/fd85fb070c.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/boxicons@latest/css/boxicons.min.css">
    <link rel="stylesheet" href="../resource/css/account.css">
</head>
<body>
    <div class="container">
        <div class="form-box login">
            <?php
                session_start();
                include '../Config/koneksi.php';

                // RESET PASSWORD - GANTI PASSWORD
                if (isset($_POST['reset_password'])) {
                    $user_id = intval($_POST['reset_user_id']);
                    $new_password = md5($_POST['new_password']);
                    $update = mysqli_query($conn, "UPDATE users SET password='$new_password' WHERE id=$user_id");
                    if ($update) {
                        echo "<script>
                            Swal.fire({
                                icon: 'success',
                                title: 'Password berhasil diubah!',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                window.location.href = 'index.php';
                            });
                        </script>";
                        exit();
                    } else {
                        echo "<script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal update password!',
                            });
                        </script>";
                    }
                }

                // RESET PASSWORD - CEK DATA
                if (isset($_POST['reset_check'])) {
                    $username = $_POST['reset_username'];
                    $email = $_POST['reset_email'];
                    $telepon = $_POST['reset_telepon'];
                    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' AND email='$email' AND telepon='$telepon'");
                    $user = mysqli_fetch_assoc($cek);
                    if ($user) {
                        // Tampilkan form password baru
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                document.querySelector('.form-box.login').style.display = 'none';
                                document.querySelector('.form-box.reset').style.display = 'none';
                                document.querySelector('.form-box.newpass').style.display = 'block';
                                document.getElementById('reset_user_id').value = '".$user['id']."';
                            });
                        </script>";
                    } else {
                        echo "<script>
                            document.addEventListener('DOMContentLoaded', function() {
                                document.getElementById('resetError').innerHTML = '<span style=\"color:red;\">Data tidak cocok!</span>';
                            });
                        </script>";
                    }
                }

                // REGISTER
                if (isset($_POST['register'])) {
                    $username = $_POST['username'];
                    $email    = $_POST['email'];
                    $telepon  = $_POST['telepon'];
                    $password = md5($_POST['password']);

                    // Cek username/email sudah ada
                    $cek = mysqli_query($conn, "SELECT * FROM users WHERE username='$username' OR email='$email'");
                    if (mysqli_num_rows($cek) > 0) {
                        $error = "Username atau Email sudah terdaftar!";
                    } else {
                        $query = "INSERT INTO users (username, email, password, telepon, role) 
                                VALUES ('$username', '$email', '$password', '$telepon', 'user')";
                        if (mysqli_query($conn, $query)) {
                            echo "<script>
                            Swal.fire({
                                icon: 'success',
                                title: 'Berhasil mendaftar!',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                window.location.href = 'index.php';
                            });
                            </script>";
                            exit();
                        } else {
                            $error = "Gagal mendaftar!";
                        }
                    }
                }

                // LOGIN
                if (isset($_POST['login'])) {
                    $username = $_POST['username'];
                    $password = md5($_POST['password']); // md5 sama seperti yang disimpan
                
                    $query = "SELECT * FROM users WHERE username='$username' AND password='$password'";
                    $result = mysqli_query($conn, $query);
                    $data = mysqli_fetch_assoc($result);
                
                    if ($data) {
                        $_SESSION['username'] = $data['username'];
                        $_SESSION['role'] = $data['role'];
                        $_SESSION['user_id'] = $data['id'];
                
                        if ($data['role'] == 'admin') {
                            header("Location: ../Admin/index.php");
                        } else {
                            header("Location: ../index.php");
                        }
                        exit();
                    } else {
                        $error = "Username atau password salah!";
                    }
                }
            ?>
            <form action="" method="POST">
                <h1>Login</h1>
                <?php if (isset($error)) : ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            Swal.fire({
                                icon: 'error',
                                title: 'Login Gagal',
                                text: '<?php echo $error; ?>',
                            });
                        });
                    </script>
                <?php endif; ?>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required autocomplete="off">
                    <i class='bx  bx-user' ></i> 
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required autocomplete="new-password">
                    <i class='bx  bx-lock'  ></i> 
                </div>
                <div class="forgot-link">
                    <a href="#" id="showResetForm">Forgot Password?</a>
                </div>
                <button type="submit" class="btn" name="login">Login</button>
                <p>or Login with Google</p>
                <div class="social-icons">
                    <?php
                    require_once '../auth/config-google.php';
                    $loginURL = $client->createAuthUrl();
                    ?>
                    <a href="<?= $loginURL ?>" title="Login with Google">
                        <i class="fab fa-google" style="font-size: 24px; color: #db4437;"></i>
                    </a>
                </div>
            </form>
        </div>

        <div class="form-box reset" style="display:none;">
            <form action="" method="POST">
                <h1>Reset Password</h1>
                <div class="input-box">
                    <input type="text" name="reset_username" placeholder="Username" required autocomplete="off">
                    <i class='bx bx-user'></i>
                </div>
                <div class="input-box">
                    <input type="email" name="reset_email" placeholder="Email" required autocomplete="off">
                    <i class="fa-solid fa-envelope"></i>
                </div>
                <div class="input-box">
                    <input type="tel" name="reset_telepon" placeholder="No Handphone" required autocomplete="off">
                    <i class="fa-solid fa-phone"></i>
                </div>
                <button type="submit" class="btn" name="reset_check">Reset Password</button>
                <div id="resetError"></div>
            </form>
        </div>

        <div class="form-box newpass" style="display:none;">
            <form action="" method="POST">
                <h1>Password Baru</h1>
                <input type="hidden" name="reset_user_id" id="reset_user_id">
                <div class="input-box">
                    <input type="password" name="new_password" placeholder="Password Baru" required>
                    <i class='bx bx-lock'></i>
                </div>
                <button type="submit" class="btn" name="reset_password">Ganti Password</button>
            </form>
        </div>

        <div class="form-box register">
            <?php
                // ... kode register tetap ...
            ?>
            <form action="" method="POST">
                <h1>Registration</h1>
                <?php if (isset($error)) : ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', function () {
                            Swal.fire({
                                icon: 'error',
                                title: 'Registrasi Gagal',
                                text: '<?php echo $error; ?>',
                            });
                        });
                    </script>
                <?php endif; ?>
                <div class="input-box">
                    <input type="text" name="username" placeholder="Username" required autocomplete="off"> 
                    <i class='bx  bx-user'></i> 
                </div>
                <div class="input-box">
                    <input type="email" name="email" placeholder="Email" required autocomplete="off">
                    <i class="fa-solid fa-envelope"></i> 
                </div>
                <div class="input-box">
                    <div style="display: flex; align-items: center;">
                        <span style="padding: 10px; background: #eee; border: 1px solid #ccc; border-right: none; border-radius: 6px 0 0 6px;">+62</span>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="telepon" 
                            placeholder="81234567890" 
                            inputmode="numeric" 
                            pattern="[0-9]{9,15}" 
                            required 
                            autocomplete="off" 
                            style="flex: 1; padding: 10px; border: 1px solid #ccc; border-radius: 0 6px 6px 0;"
                        >
                        <i class="fa-solid fa-phone"></i>
                    </div>
                </div>
                <div class="input-box">
                    <input type="password" name="password" placeholder="Password" required autocomplete="new-password">
                    <i class='bx  bx-lock'></i> 
                </div>
                <button type="submit" class="btn" name="register">Register</button>
                <p>or Register with Google</p>
                <div class="social-icons">
                    <?php
                    require_once '../auth/config-google.php';
                    $loginURL = $client->createAuthUrl();
                    ?>
                    <a href="<?= $loginURL ?>" title="Login with Google">
                        <i class="fab fa-google" style="font-size: 24px; color: #db4437;"></i>
                    </a>
                </div>
            </form>
        </div>
        <div class="toggle-box">
            <div class="toggle-panel toggle-left">
                <h1>Hello, Welcome</h1>
                <p>Don't have an account?</p>
                <button class="btn register-btn">Register</button>
            </div>
            <div class="toggle-panel toggle-right">
                <h1>Welcome Back!</h1>
                <p>Already have an account?</p>
                <button class="btn login-btn">Login</button>
            </div>
        </div>
    </div>

    
    <script src="https://unpkg.com/boxicons@2.1.3/dist/boxicons.js"></script>
    <script src="../resource/js/account.js"></script>
    <?php if (isset($error)) : ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        Swal.fire({
            icon: 'error',
            title: 'Terjadi Kesalahan',
            text: '<?php echo $error; ?>',
        });
    });
    </script>
    <?php endif; ?>
    <script>
    document.getElementById('showResetForm').onclick = function(e) {
        e.preventDefault();
        document.querySelector('.form-box.login').style.display = 'none';
        document.querySelector('.form-box.reset').style.display = 'block';
        document.querySelector('.form-box.newpass').style.display = 'none';
    };
    </script>
</body>
</html>