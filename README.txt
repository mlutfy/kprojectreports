Since 7.x-1.x, kprojectreport depends on:

* http://drupal.org/project/mailsystem
* http://drupal.org/project/htmlmail

This supports sending reports in HTML format.

Once you have enabled the modules, go to:

* /admin/config/system/mailsystem

Make sure that you have the following settings:

* Site-wide Mail module class: HTMLMailSystem
* HTML Mail module class: HTMLMailSystem
* Koumbit Project Reports module class: HTMLMailSystem

Normally, the class is set when the module is enabled, only to
affect the kprojectreports module. I haven't figured out how to
make that work correctly. If you do, let me know!

