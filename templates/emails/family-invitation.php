<?php
/**
 * Family Invitation Email Template
 *
 * Available variables:
 * @var string $site_name     Site name
 * @var string $site_url      Site URL
 * @var string $accept_url    Auto-login acceptance URL
 * @var string $email         Recipient email
 * @var object $user          User object
 * @var object|null $parent   Parent user object (may be null)
 * @var string|null $password Password for new users (null for existing users)
 *
 * @package    GHL_CRM_Integration
 * @subpackage Templates/Emails
 */

defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<style>
		body { 
			margin: 0; 
			padding: 0; 
			font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
			line-height: 1.6;
			color: #2c3e50;
			background-color: #f4f4f4;
		}
		.email-wrapper {
			max-width: 600px;
			margin: 20px auto;
			background: #ffffff;
			border-radius: 8px;
			overflow: hidden;
			box-shadow: 0 2px 8px rgba(0,0,0,0.1);
		}
		.email-header {
			background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
			padding: 30px 20px;
			text-align: center;
			color: #ffffff;
		}
		.email-header h1 {
			margin: 0;
			font-size: 24px;
			font-weight: 600;
		}
		.email-body {
			padding: 40px 30px;
		}
		.email-body h2 {
			color: #2c3e50;
			font-size: 20px;
			margin: 0 0 20px;
		}
		.email-body p {
			margin: 0 0 15px;
			color: #555;
			font-size: 15px;
		}
		.credentials-box {
			background: #f8f9fa;
			border-left: 4px solid #007cba;
			padding: 20px;
			margin: 20px 0;
			border-radius: 4px;
		}
		.credentials-box p {
			margin: 8px 0;
			font-family: monospace;
			color: #2c3e50;
		}
		.credentials-box strong {
			display: inline-block;
			width: 100px;
			color: #007cba;
		}
		.accept-button {
			display: inline-block;
			background: linear-gradient(135deg, #007cba 0%, #005a87 100%);
			color: #ffffff !important;
			padding: 16px 40px;
			text-decoration: none;
			border-radius: 6px;
			font-weight: 600;
			font-size: 16px;
			text-align: center;
			margin: 25px 0;
			box-shadow: 0 4px 12px rgba(0, 124, 186, 0.3);
			transition: all 0.3s ease;
		}
		.accept-button:hover {
			transform: translateY(-2px);
			box-shadow: 0 6px 16px rgba(0, 124, 186, 0.4);
		}
		.button-container {
			text-align: center;
			margin: 30px 0;
		}
		.email-footer {
			background: #f8f9fa;
			padding: 20px 30px;
			text-align: center;
			color: #7f8c8d;
			font-size: 13px;
			border-top: 1px solid #e5e7eb;
		}
		.email-footer p {
			margin: 5px 0;
		}
		.email-footer a {
			color: #007cba;
			text-decoration: none;
		}
	</style>
</head>
<body>
	<div class="email-wrapper">
		<div class="email-header">
			<h1><?php echo esc_html( $site_name ); ?></h1>
		</div>
		
		<div class="email-body">
			<?php if ( $password ) : ?>
				<h2><?php esc_html_e( 'Welcome! Your Account Has Been Created', 'ghl-crm-integration' ); ?></h2>
				<p><?php esc_html_e( 'Hello,', 'ghl-crm-integration' ); ?></p>
				<p>
					<?php
					printf(
						/* translators: 1: Parent display name, 2: Site name */
						esc_html__( '%1$s has created an account for you on %2$s and invited you to join their family account.', 'ghl-crm-integration' ),
						esc_html( $parent ? $parent->display_name : __( 'The site administrator', 'ghl-crm-integration' ) ),
						'<strong>' . esc_html( $site_name ) . '</strong>'
					);
					?>
				</p>
				
				<div class="credentials-box">
					<p><strong><?php esc_html_e( 'Username:', 'ghl-crm-integration' ); ?></strong> <?php echo esc_html( $user->user_login ); ?></p>
					<p><strong><?php esc_html_e( 'Email:', 'ghl-crm-integration' ); ?></strong> <?php echo esc_html( $email ); ?></p>
					<p><strong><?php esc_html_e( 'Password:', 'ghl-crm-integration' ); ?></strong> <?php echo esc_html( $password ); ?></p>
				</div>
				
				<p><strong><?php esc_html_e( 'Click the button below to accept the invitation and access your account automatically:', 'ghl-crm-integration' ); ?></strong></p>
			<?php else : ?>
				<h2><?php esc_html_e( "You've Been Invited!", 'ghl-crm-integration' ); ?></h2>
				<p><?php esc_html_e( 'Hello,', 'ghl-crm-integration' ); ?></p>
				<p>
					<?php
					printf(
						/* translators: 1: Parent display name, 2: Site name */
						esc_html__( '%1$s has invited you to join their family account on %2$s.', 'ghl-crm-integration' ),
						esc_html( $parent ? $parent->display_name : __( 'A user', 'ghl-crm-integration' ) ),
						'<strong>' . esc_html( $site_name ) . '</strong>'
					);
					?>
				</p>
				
				<p><strong><?php esc_html_e( 'Click the button below to accept the invitation:', 'ghl-crm-integration' ); ?></strong></p>
			<?php endif; ?>
			
			<div class="button-container">
				<a href="<?php echo esc_url( $accept_url ); ?>" class="accept-button">
					<?php esc_html_e( 'Accept Invitation', 'ghl-crm-integration' ); ?>
				</a>
			</div>
			
			<?php if ( $password ) : ?>
				<p style="color: #7f8c8d; font-size: 13px; margin-top: 20px;">
					<em><?php esc_html_e( 'For security, we recommend changing your password after your first login.', 'ghl-crm-integration' ); ?></em>
				</p>
			<?php endif; ?>
			
			<p style="color: #95a5a6; font-size: 13px; margin-top: 30px;">
				<?php esc_html_e( "If you're unable to click the button, copy and paste this link into your browser:", 'ghl-crm-integration' ); ?><br>
				<span style="word-break: break-all; color: #007cba;"><?php echo esc_url( $accept_url ); ?></span>
			</p>
		</div>
		
		<div class="email-footer">
			<p>&copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> <?php echo esc_html( $site_name ); ?>. <?php esc_html_e( 'All rights reserved.', 'ghl-crm-integration' ); ?></p>
			<p><a href="<?php echo esc_url( $site_url ); ?>"><?php echo esc_html( $site_url ); ?></a></p>
		</div>
	</div>
</body>
</html>
