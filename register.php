<?php
require_once __DIR__ . '/includes/session.php';
caz_start_portal_session(false);
require_once __DIR__ . '/includes/captcha.php';
require_once __DIR__ . '/includes/identity_verification.php';
header('Cache-Control: no-store, max-age=0');
$registrationValues = $_SESSION['caz_registration_values'] ?? [];
unset($_SESSION['caz_registration_values']);
function caz_registration_value(string $key): string {
    global $registrationValues;
    return htmlspecialchars((string)($registrationValues[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Caz Store</title>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;700&family=Michroma&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #050505; 
            background-image:
                radial-gradient(ellipse at 0% 0%, rgba(223,44,41,0.10) 0%, transparent 45%),
                radial-gradient(ellipse at 100% 100%, rgba(223,44,41,0.10) 0%, transparent 45%),
                radial-gradient(circle, rgba(255,255,255,0.06) 1px, transparent 1px);
            margin: 0; 
            display: flex; 
            justify-content: center; 
            align-items: center; 
            min-height: 100vh; 
            font-family: 'Michroma', sans-serif; 
            padding: 20px;
        }
        
        .container { 
            width: 100%;
            max-width: 750px;
            box-sizing: border-box;
        }
        
        .login-card { 
            background-color: #0a0a0a; 
            border: 1px solid #141414;
            padding: 45px 40px;
            text-align: left; 
            box-sizing: border-box;
            width: 100%;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .full-width {
            grid-column: 1 / -1;
        }
        
        .input-group {
            display: flex;
            flex-direction: column;
        }

        .account-field-help { color: #c4e5e7; font: 400 13px/1.5 Arial, sans-serif; margin-top: 7px; }

        label { 
            color: #555555; 
            font-family: 'JetBrains Mono', monospace;
            font-size: 11px;
            text-transform: uppercase;
            font-weight: bold; 
            margin-bottom: 8px; 
            letter-spacing: 1px;
        }
        
        input, select { 
            width: 100%; 
            padding: 14px 16px;
            background-color: #050505;
            border: 1px solid #1c1c1c; 
            color: #ffffff;
            font-family: 'JetBrains Mono', monospace;
            font-size: 14px;
            box-sizing: border-box; 
            outline: none;
            transition: all 0.2s ease;
        }

        select {
            appearance: none;
            cursor: pointer;
            color: #666;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23666' d='M6 8L1 3h10z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 14px center;
        }

        select.has-value {
            color: #ffffff;
        }
        
        input:focus, select:focus {
            border-color: #e53935;
            background-color: #0d0d0d;
        }

        .address-box {
            background: #070707;
            border: 1px solid #141414;
            padding: 20px;
            margin-top: 5px;
        }

        .address-box-title {
            color: #888;
            font-family: 'Michroma', sans-serif;
            font-size: 12px;
            margin-bottom: 15px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .address-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .action { 
            display: flex; 
            justify-content: flex-end; 
            margin-top: 15px;
        }
        
        .login-btn { 
            width: 100%; 
            background-color: transparent; 
            color: #ffffff; 
            border: 1px solid #222222; 
            padding: 15px; 
            cursor: pointer; 
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            text-transform: uppercase;
            font-weight: bold; 
            letter-spacing: 1px;
            transition: all 0.2s ease; 
        }
        
        .login-btn:hover { 
            background-color: #e53935; 
            border-color: #e53935;
        }
        
        .footer-link { 
            text-align: center; 
            margin-top: 25px; 
            color: #444444; 
            font-size: 13px;
            font-family: 'Space Grotesk', sans-serif; 
        }
        
        .footer-link a { 
            color: #e53935; 
            text-decoration: none; 
            font-weight: bold; 
            margin-left: 4px;
            transition: color 0.2s ease;
        }
        
        .footer-link a:hover {
            color: #ffffff;
        }
        
        .error-msg { 
            color: #ff5252; 
            border-left: 3px solid #ff5252;
            background: rgba(255, 82, 82, 0.05); 
            padding: 14px; 
            margin-bottom: 25px; 
            font-size: 14px; 
            text-align: center;
        }

        @media (max-width: 600px) {
            .form-grid, .address-grid {
                grid-template-columns: 1fr;
            }
            .login-card {
                padding: 30px 20px;
            }
        }
    </style>
<link rel="stylesheet" href="css/refined.css?v=20260920-shopping"><script defer src="js/refined.js?v=20260910-release"></script><link rel="stylesheet" href="css/experience.css?v=20260921-location"><script defer src="js/experience.js?v=20260926-teal"></script>
<link rel="stylesheet" href="css/background-theme.css?v=20260926-teal">
<link rel="stylesheet" href="css/glass-panels.css?v=20260926-teal">
<link rel="stylesheet" href="css/captcha.css?v=<?= filemtime(__DIR__ . '/css/captcha.css') ?>">
<link rel="stylesheet" href="css/identity-verification.css?v=<?= filemtime(__DIR__ . '/css/identity-verification.css') ?>">
<script defer src="js/identity-verification.js?v=20260930"></script>
<script defer src="js/captcha.js?v=20260925-teal"></script>
<script defer src="js/account-validation.js?v=<?= filemtime(__DIR__ . '/js/account-validation.js') ?>"></script>
</head>
<body class="portal-body caz-themed caz-register-page">
<?php require __DIR__ . '/includes/background.php'; ?>
    <div class="container">
        <div class="login-card"><h1 class="auth-heading">Create your account</h1>
            <form action="register_check.php" method="POST" enctype="multipart/form-data">
                
                <?php if (isset($_GET['error'])): ?>
                    <div class="error-msg full-width">
                        <?php echo htmlspecialchars($_GET['error']); ?>
                    </div>
                <?php endif; ?>

                <div class="form-grid">
                    <div class="input-group">
                        <label for="reg-name">Username / Full Name</label>
                        <input id="reg-name" type="text" name="name" placeholder="Enter full name" required autocomplete="name" maxlength="100" pattern="\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)*" aria-describedby="reg-name-help" data-account-name value="<?php echo caz_registration_value('name'); ?>">
                        <small id="reg-name-help" class="account-field-help">Letters and spaces only.</small>
                    </div>
                    
                    <div class="input-group">
                        <label for="reg-email">Email Address</label>
                        <input id="reg-email" type="email" name="email" placeholder="Enter email" required autocomplete="email" value="<?php echo caz_registration_value('email'); ?>">
                    </div>

                    <div class="input-group">
                        <label for="reg-contact">Contact Number</label>
                        <input id="reg-contact" type="tel" name="contact_number" placeholder="09XXXXXXXXX" required autocomplete="tel" maxlength="11" minlength="11" inputmode="numeric" pattern="09[0-9]{9}" data-account-phone aria-describedby="reg-contact-help" value="<?php echo caz_registration_value('contact_number'); ?>">
                        <small id="reg-contact-help" class="account-field-help">Exactly 11 digits, starting with 09.</small>
                    </div>

                    <div class="input-group">
                        <label for="reg-password">Password</label>
                        <input id="reg-password" type="password" name="password" placeholder="Create a password" autocomplete="new-password" required minlength="8" maxlength="72" pattern="(?=.*[\p{P}\p{S}]).{8,72}" data-account-password aria-describedby="reg-password-help">
                        <small id="reg-password-help" class="account-field-help">At least 8 characters, including a special character such as !, @ or #.</small>
                    </div>

                    <div class="full-width address-box">
                        <div class="address-box-title">Shipping Address</div>
                        <div class="address-grid">
                            <div class="input-group">
                                <label for="reg-house">House / Unit No.</label>
                                <input type="text" id="reg-house" name="address_house" placeholder="e.g. 12B" autocomplete="off" value="<?php echo caz_registration_value('address_house'); ?>">
                            </div>
                            <div class="input-group">
                                <label for="reg-zip">ZIP Code <span aria-hidden="true">*</span></label>
                                <input type="text" id="reg-zip" name="address_zip" placeholder="e.g. 3500" maxlength="10" value="<?php echo caz_registration_value('address_zip'); ?>"
                                    inputmode="numeric" autocomplete="off"
                                    oninput="this.value=this.value.replace(/[^0-9]/g,'')" required>
                            </div>
                            <div class="input-group">
                                <label for="reg-street">Street <span aria-hidden="true">*</span></label>
                                <input type="text" id="reg-street" name="address_street" placeholder="e.g. Rizal" autocomplete="off" required value="<?php echo caz_registration_value('address_street'); ?>"
                                    onblur="var v=this.value.trim();if(v&&!/St\.?\s*$/i.test(v))this.value=v+' St.';">
                            </div>
                            <div class="input-group">
                                <label for="reg-barangay">Barangay <span aria-hidden="true">*</span></label>
                                <input type="text" id="reg-barangay" name="address_barangay" placeholder="e.g. San Antonio" autocomplete="off" required value="<?php echo caz_registration_value('address_barangay'); ?>">
                            </div>
                            <div class="input-group">
                                <label for="reg-city">City / Municipality <span aria-hidden="true">*</span></label>
                                <input type="text" id="reg-city" name="address_city" placeholder="e.g. Tuguegarao City" autocomplete="off" required value="<?php echo caz_registration_value('address_city'); ?>">
                            </div>
                            <div class="input-group">
                                <label for="reg-province">Province <span aria-hidden="true">*</span></label>
                                <select id="reg-province" name="address_province" required onchange="this.classList.add('has-value')">
                                    <?php if (!empty($registrationValues['address_province'])): ?><option selected value="<?php echo caz_registration_value('address_province'); ?>"><?php echo caz_registration_value('address_province'); ?></option><?php endif; ?>
                                    <option value="" disabled <?php if (empty($registrationValues['address_province'])) echo 'selected'; ?>>Select province…</option>
                                    <option>Abra</option>
                                    <option>Agusan del Norte</option>
                                    <option>Agusan del Sur</option>
                                    <option>Aklan</option>
                                    <option>Albay</option>
                                    <option>Antique</option>
                                    <option>Apayao</option>
                                    <option>Aurora</option>
                                    <option>Basilan</option>
                                    <option>Bataan</option>
                                    <option>Batanes</option>
                                    <option>Batangas</option>
                                    <option>Benguet</option>
                                    <option>Biliran</option>
                                    <option>Bohol</option>
                                    <option>Bukidnon</option>
                                    <option>Bulacan</option>
                                    <option>Cagayan</option>
                                    <option>Camarines Norte</option>
                                    <option>Camarines Sur</option>
                                    <option>Camiguin</option>
                                    <option>Capiz</option>
                                    <option>Catanduanes</option>
                                    <option>Cavite</option>
                                    <option>Cebu</option>
                                    <option>Compostela Valley</option>
                                    <option>Cotabato</option>
                                    <option>Davao de Oro</option>
                                    <option>Davao del Norte</option>
                                    <option>Davao del Sur</option>
                                    <option>Davao Occidental</option>
                                    <option>Davao Oriental</option>
                                    <option>Dinagat Islands</option>
                                    <option>Eastern Samar</option>
                                    <option>Guimaras</option>
                                    <option>Ifugao</option>
                                    <option>Ilocos Norte</option>
                                    <option>Ilocos Sur</option>
                                    <option>Iloilo</option>
                                    <option>Isabela</option>
                                    <option>Kalinga</option>
                                    <option>La Union</option>
                                    <option>Laguna</option>
                                    <option>Lanao del Norte</option>
                                    <option>Lanao del Sur</option>
                                    <option>Leyte</option>
                                    <option>Maguindanao</option>
                                    <option>Maguindanao del Norte</option>
                                    <option>Maguindanao del Sur</option>
                                    <option>Marinduque</option>
                                    <option>Masbate</option>
                                    <option>Metro Manila</option>
                                    <option>Misamis Occidental</option>
                                    <option>Misamis Oriental</option>
                                    <option>Mountain Province</option>
                                    <option>Negros Occidental</option>
                                    <option>Negros Oriental</option>
                                    <option>Northern Samar</option>
                                    <option>Nueva Ecija</option>
                                    <option>Nueva Vizcaya</option>
                                    <option>Occidental Mindoro</option>
                                    <option>Oriental Mindoro</option>
                                    <option>Palawan</option>
                                    <option>Pampanga</option>
                                    <option>Pangasinan</option>
                                    <option>Quezon</option>
                                    <option>Quirino</option>
                                    <option>Rizal</option>
                                    <option>Romblon</option>
                                    <option>Samar</option>
                                    <option>Sarangani</option>
                                    <option>Siquijor</option>
                                    <option>Sorsogon</option>
                                    <option>South Cotabato</option>
                                    <option>Southern Leyte</option>
                                    <option>Sultan Kudarat</option>
                                    <option>Sulu</option>
                                    <option>Surigao del Norte</option>
                                    <option>Surigao del Sur</option>
                                    <option>Tarlac</option>
                                    <option>Tawi-Tawi</option>
                                    <option>Zambales</option>
                                    <option>Zamboanga del Norte</option>
                                    <option>Zamboanga del Sur</option>
                                    <option>Zamboanga Sibugay</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" name="address" id="reg-address-combined">
                    <input type="hidden" name="role" value="customer">

                    <?php caz_identity_form_fields($registrationValues); ?>
                    <?php caz_captcha_form('register'); ?>

                    <div class="full-width action">
                        <button type="submit" class="login-btn">Register</button>
                    </div>
                </div>
            </form>
        </div>
        
        <div class="footer-link">
            <p>Already have an account? <a href="login.php">Login</a></p>
        </div>
    </div>

<script>
    document.querySelector('form').addEventListener('submit', function(e) {
        const house    = document.getElementById('reg-house').value.trim();
        const street   = document.getElementById('reg-street').value.trim();
        const barangay = document.getElementById('reg-barangay').value.trim();
        const city     = document.getElementById('reg-city').value.trim();
        const province = document.getElementById('reg-province').value.trim();
        const zip      = document.getElementById('reg-zip').value.trim();

        if (!street || !barangay || !city || !province || !zip) {
            e.preventDefault();
            alert('Please fill in all required address fields (Street, Barangay, City, Province, ZIP).');
            return;
        }

        const parts = [];
        if (house)    parts.push(house);
        if (street)   parts.push(street);
        if (barangay) parts.push('Brgy. ' + barangay);
        if (city)     parts.push(city);
        if (province) parts.push(province);
        if (zip)      parts.push(zip);

        document.getElementById('reg-address-combined').value = parts.join(', ');
    });
</script>
</body>
</html>
