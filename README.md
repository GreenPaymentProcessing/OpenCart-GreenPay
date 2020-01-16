# OpenCart-GreenPay
GreenPay gateway plugin for OpenCart eCommerce system

WARNING: This repository and plugin are currently under development and are not tested in production environments. Usage of this plugin is done at your own risk!

## Prerequisites
1. Your server must have OpenCart version 3.x or greater installed. 
2. Your server must have a domain name and be secured via SSL certificate. All requests made to our API must be done from HTTPS
3. You must allow extensions to write to the API folder in OpenCart. 

## Allow Extensions to Write to the API Folder in OpenCart
Our extension adds custom API controllers so that our API can update the status of orders in your store whenever we receive a check payment, update its verification status, or process the check so that your store has the most up to date information about your payment as possible. In order to enable extensions to add to the OpenCart API, you will need to edit one of the OpenCart core files before beginning with the installation. 

First, connect to your web server using your favorite FTP client and navigate to your OpenCart installation's root direction. From there, find the file at the following path: `<OpenCart Root>/admin/controller/marketplace/install.php`. Open that file in a text editor. Look for the following section of code: 

```php
// A list of allowed directories to be written to
$allowed = array(
    'admin/controller/extension/',
    'admin/language/',
    'admin/model/extension/',
    'admin/view/image/',
    'admin/view/javascript/',
    'admin/view/stylesheet/',
    'admin/view/template/extension/',
    'catalog/controller/extension/',
    'catalog/language/',
    'catalog/model/extension/',
    'catalog/view/javascript/',
    'catalog/view/theme/',
    'system/config/',
    'system/library/',
    'image/catalog/'
);
```

This defines what sections OpenCart Extensions are allowed to write to. We need to add one, so change that whole section to this: 

```php
// A list of allowed directories to be written to
$allowed = array(
    'admin/controller/extension/',
    'admin/language/',
    'admin/model/extension/',
    'admin/view/image/',
    'admin/view/javascript/',
    'admin/view/stylesheet/',
    'admin/view/template/extension/',
    'catalog/controller/api/',
    'catalog/controller/extension/',
    'catalog/language/',
    'catalog/model/extension/',
    'catalog/view/javascript/',
    'catalog/view/theme/',
    'system/config/',
    'system/library/',
    'image/catalog/'
);
```

We added `catalog/controller/api/` as a path in the middle there. Once you're done with that, save the changes to this file and, if you're editing via FTP make sure to upload the file back. If you're editing on the server directly through SSH then just save the file and the changes should take effect immediately and you can move onto the installation.

## Installation via FTP
Clone or download this repository. Use your favorite FTP client to connect to your server and navigate to your OpenCart's root directory. From there, copy all the files inside the `upload` folder into that root directory. Do not copy the `upload` folder itself, just the folders and files inside it.

Once those files are uploaded via FTP, you can login to your OpenCart admin and navigate to Extensions > Payments and enable the GreenPay plugin.

## Installation via Dashboard
Clone or download this repository to your local machine. Login to your OpenCart Admin Panel and go to Extensions > Installer. Click the `Upload File` button which will open a file browser. Navigate to the folder you just copied and select the `greenpay.ocmod.zip` file for upload. Once the progress is complete, you can navigate to Extensions > Payments and enable the GreenPay plugin.

## First Steps
Before we can get to the configuration, let's make sure we have all the values we need. First, make sure you have your Green API credentials including your Client ID and API Password in front of you. Secondly, we need to get an API Key from Open Cart for your store.

Login to your OpenCart Admin Panel and Navigate to System > Users > API. In a clean installed store, there's one key here called "Default" so you can edit that or you can add a new one especially for Green if you like. Click the edit button and you'll be taken to a simple screen that has three fields, `API Username`, `API Key`, and `Status`. Under the API Key field there's a button to `Generate`. Go ahead and click that and you should see the value in API Key refresh with something new. Copy down both the API Username and the API Key from this page and then hit the Save icon up at the top right of the screen.

## Configuration
After you have uploaded the files, you can find the GreenPay configuration settings by navigating to Extensions > Payments and you'll find GreenPay in the list of accepted payment methods. Here you can configure the following settings:
- Enabled: When this is checked, GreenPay will display as a payment option if your configuration is valid.
- Mode: Values are `Live` or `Test`. In Live mode, payments are entered into the payment processing system and will incur fees and be processed to your bank. In Test mode, payments are entered into our Sandbox system which does not incur fees and will not process the checks allowing you to test your store and the behavior of the plugin.
- Client ID: This is your Green Merchant ID which is usually a 6-7 digit number. You can find this by logging into your Green Portal and looking for your MID in the top left hand corner.*
- API Password: This is your Green API Password which is automatically generated by the system. You can receive these credentials by logging into your Green Portal and navigating to My Account > API.
- OpenCart API Username: The value we copied down earlier from `API Username`
- OpenCart API Key: The value we copied down earlier from `API Key`

\*Note that in Test mode, your Client ID and API Password are a separate pair of credentials than in Live mode. If you are receiving errors about your credentials being incorrect, it may be because you have the wrong credentials for the selected mode. If you are unsure which credentials you have, please contact support at support@green.money

### Configuration Errors and Warnings
A few potential errors could display during the process of saving your configuration. Your store is not ready to receive or test payments at checkout until you receive a success message so we'll need to resolve those before you can move forward.

#### Please check the form fields for errors and try to save again. All fields are required for the plugin to function
This one is pretty simple! You just missed a field. All of the fields are required so they must be filled out.

#### GreenPay Gateway is not enabled
This just notes that the gateway has been set to disabled which means you won't be able to take payments during checkout. To resolve this, make sure you check the box next to Enabled before saving!

#### You are in test mode! Go live when you are ready
As explained above, when in Test mode, no payments will actually be generated in Green. Instead, the plugin and API simply emulate the process of making the check including changing the order status and adding notes. This can be useful but make sure to change this to live when you're ready. 

#### Green API credentials are invalid. Please double check your Green API credentials and try again.
The Green API credentials you used didn't validate with our API. It's most likely that you are using the incorrect credentials or miscopied them! Make sure you have them absolutely correct and, if you need to, you can generate new ones in your Green Portal by logging in and navigating to My Account > API. 

#### Green was unable to contact your store's API using the specified key. Please make sure you have whitelisted Green's IP address and that you have copied the key correctly.
Your Green API credentials validated, but our server was unable to reach out to your store using the Opencart API Username and Key you specified. So first step will be to verify you have those correctly copied! If you correct that and still receive the error, it could caused by a file not being correctly copied during the installation process of the extension. Before we installed the plugin, we had to correct a file to make sure we could upload to the API folder. We would recommend going back to that step and making sure it was correctly configured. If necessary, you may need to remove the extension and reinstall it and then attempt again. 
