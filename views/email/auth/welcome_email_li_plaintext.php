Thank you for registering at <?=APP_NAME?>'s website.

This email confirms that you used your LinkedIn account to register.

We would appreciate it if you could take a second to verify your email address using the link below, we do this to ensure the integrity of our database.

{unwrap}<?=site_url( 'auth/activate/' . $user->id . '/' . $user->activation_code )?>{/unwrap}