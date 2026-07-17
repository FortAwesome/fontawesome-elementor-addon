=== Font Awesome Elementor Addon ===
Contributors: fontawesome, mlwilkerson, frrrances
Stable tag: 0.1.0-alpha2
Tags: FontAwesome, Elementor, icon, svg icon
Requires at least: 5.8
Tested up to: 6.9
Requires PHP: 7.4
License: GPLv3
License URI: https://www.gnu.org/licenses/gpl-3.html

The official way to use Font Awesome Pro icons in Elementor, brought to you by the Font Awesome team.

== Description ==

The official way to use Font Awesome Pro icons in Elementor, brought to you by the Font Awesome team.

= Features =

Our official Font Awesome Elementor Addon makes it easy to add your your Font Awesome Pro Kit to the Elementor Icon Library. It works by automatically setting up your Kit for self-hosting on your own WordPress site. You can use it to add Font Awesome icons anywhere in Elementor that the Elementor Icon Library works, such as the Icon, Icon Block, Icon List widgets, and more.

Works with any icons in your Font Awesome Pro Kit, including the latest icons from Version 7, in any style. Works with custom icons and duotone custom icons in you Pro Kit, including those created with the Icon Wizard!

When visitors to your site view pages, icons on those pages are self-hosted--served from your own WordPress site. No CDN is used.

Requires an active Font Awesome Pro account that includes the Kit Download feature. (Font Awesome Pro Lite plans do not include Kit Download.)

== Installation ==

= From the Plugins Directory in WordPress Admin =

From the "Add Plugins" page in WordPress admin:

1. Search the plugins directory by `author: fontawesome`

2. Click "Install" on the Font Awesome Elementor Addon plugin in the search results

3. Click "Activate"

= Installing a Zip Archive =

1. Click Download on this plugin's directory entry to get the `.zip` file

2. On the "Add Plugins" page in WordPress admin, click "Upload Plugin" and choose that `.zip` file

= Access Font Awesome Plugin Settings =

Once you activate the Font Awesome Elementor Addon plugin, you will see Font Awesome Elementor Addon in the Settings menu in your WordPress admin area.

= Configuration =

To use the plugin, you will need to have a Font Awesome Pro Kit. If you don't have one, you can get one from https://fontawesome.com.

In your Font Awesome account, create an [API Token](https://fontawesome.com/account/tokens) with scope "Download Kits".

Create or visit one of your existing [Font Awesome Pro Kits](https://fontawesome.com/kits). On the Kit's "Set Up" tab, find the Kit embed code, that looks like this:

```
<script src="https://kit.fontawesome.com/f721934a08.js" crossorigin="anonymous"></script>
```

The Kit Token is the part of the URL in the `src` attribute, just before the `.js`. In this example: `f721934a08`.

In the Font Awesome Elementor Addon settings page, enter your API Token and Kit Token and click "Save Changes". When those are saved successfully, click ""Setup Kit". This will download your Kit and set it up for self-hosting on your WordPress site. Once that's done, you can start using your Font Awesome Pro icons in Elementor!

If you make changes to your Kit in your Font Awesome account, such as adding or removing icons, return to the settings page and click "Refresh Kit" to automatically update the Kit's self-hosting on your WordPress. This changes will be reflected immediately in the Elementor Icon Library.

= Kit Requirements =

Due to some technical requirements of how Elementor works, your Font Awesome Pro Kit must include only a limited number of icons. An icon count of 500 is known to work as expected.

This requires using a subsetted kit.

It could be a custom-subsetted kit, where you select each individual icon to be included.

Or it could be an auto-subsetted kit, where you include a whole style. However, the only styles currently that contain fewer than 500 icons are the Pro Plus styles, like Notdog Solid or Chisel Regular.

= Recommend Including the Default Icon =

Elementor uses the "star" in the Classic Solid style as the default icon in the icon preview before you select one in the Icon Library. If you want that default icon preview to work as expected, you must include the "star" icon in the Classic Solid style in your Kit, which must be a custom-subsetted kit.

This is only a cosmetic issue, and doesn't affect the actual icons you can select and use in Elementor. If you don't include the "star" icon in the Classic Solid style, then the default icon preview will just be blank or show an empty box.

= Account Requirement =

This plugin requires an active Font Awesome Pro account that includes the Kit Download feature. (Font Awesome Pro Lite plans do not include Kit Download.)

== Changelog ==
= 0.1.0-alpha2 =

* Adding more error diagnostics to troubleshoot setup and compatibility issues.

= 0.1.0-alpha1 =

* Initial alpha release.
