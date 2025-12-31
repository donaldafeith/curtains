=== The Iron Curtain ===
Contributors: donalda
Tags: security, hardening, xml-rpc, user enumeration, bloat-free
Requires at least: 5.0
Tested up to: 6.9
Stable tag: 1.1.0
Requires PHP: 7.4
License: GPLv2 or later

The "No-BS" security hardener. It blocks the 5 most common ways hackers get in, uses 0% CPU, creates 0 database tables, and proves it works with a built-in self-audit tool.

== Description ==

Stop installing bloated security suites that slow down your website.

Most security plugins are like installing a CCTV camera system, a guard dog, and a laser grid—they run constant scans, fill your database with logs, and nag you to upgrade to "Premium."

**The Iron Curtain** is different. It is not a scanner. It is a deadbolt.

It simply turns off the dangerous, outdated features that WordPress leaves open by default. It runs no background processes. It writes nothing to your database. It just slams the door shut on bots.

### 🛡️ The 5 Shields
When you activate The Iron Curtain, you get a simple dashboard to toggle these 5 essential protections:

1.  **Kill XML-RPC:** This is the #1 way hackers brute-force your password. Unless you use the ancient WordPress mobile app, you don't need it. We kill it dead.
2.  **Stop User Enumeration:** Hackers run bots that ask your site, "Hey, who is User #1?" Standard WordPress happily replies, "That's Donalda!" We stop this leak, disable Author Archives, and remove author links from your posts so your username stays secret.
3.  **Hide WordPress Version:** Don't advertise exactly which version you are running. We scrub the version number from your HTML source, RSS feeds, scripts, and styles.
4.  **Disable File Editor:** If a hacker *does* guess your password, the first thing they do is use the "Theme Editor" to inject malware. We remove that menu entirely.
5.  **Obfuscate Login Errors:** If you type a wrong password, WordPress tells you, "The password for user **admin** is incorrect." Oops—you just confirmed the username exists! We change all errors to a generic "Invalid credentials."

### 🔬 Trust But Verify (New in 1.1)
How do you know a security plugin is actually working? Usually, you just have to trust them.

**We don't want your trust. We want your verification.**

The Iron Curtain includes a **Self-Diagnostic Engine**. Click the "Run Security Audit" button, and the plugin will actively try to hack itself (safely!) to prove that the shields are holding.
* It tries to exploit XML-RPC.
* It tries to scan for users.
* It tries to find your version number.

If the shield is up, you get a Green Light. If not, you know exactly what to fix.

### ☕ Gratitude-Ware
This plugin is free. There is no Pro version. There are no ads.

However, saving your server from millions of bot attacks saves you CPU and electricity. If you appreciate the peace of mind, there is a small link to buy the developer a coffee/taco. It's not required, but it fuels the code.

== Installation ==

1.  Upload the `iron-curtain` folder to the `/wp-content/plugins/` directory.
2.  Activate the plugin through the 'Plugins' menu in WordPress.
3.  Go to **Settings > Iron Curtain**.
4.  Review the "Vulnerability Audit" (Red means open).
5.  Check the boxes you want to secure and click **Secure My Site**.
6.  Click **Run Full Security Audit** to watch the lights turn Green.

== Frequently Asked Questions ==

= Will this break my site? =
Unlikely. The only feature that might affect you is "Disable XML-RPC." If you use the WordPress Mobile App or Jetpack, you might need to leave that specific shield OFF. Everything else is safe for 99.9% of websites.

= What if I lock myself out? =
You can't. This plugin doesn't touch your login password or move the login URL. It just hardens the walls around it. If you decide you need the "File Editor" back, just uncheck the box and hit Save.

= Does this replace Wordfence/Sucuri? =
Think of it this way: Wordfence is a security guard who checks everyone's ID (active scanning). The Iron Curtain is a brick wall (static hardening). You can use them together, but for many simple sites, The Iron Curtain is all you need to stop the noise.

= Why did my Author Link disappear? =
That's a feature, not a bug! Clicking an author's name usually takes you to an "Author Archive" page, which reveals your username in the URL bar. To protect your identity, we remove the link and disable that archive page.

== Screenshots ==

1.  **The Dashboard:** Simple, clean, and red "OPEN" warnings before you secure the site.
2.  **The Green Lights:** The "Trust but Verify" audit showing a fully secured system.

== Changelog ==

= 1.1.0 =
* Added Self-Diagnostic Engine (Trust but Verify).
* Added "Nuclear" User Enumeration protection (blocks Request, Redirect, and Template).
* Added Author Link stripper to remove username leaks from the frontend.
* Added "Gratitude" hook.

= 1.0.0 =
* Initial release.
* Basic 5 shields implemented.
