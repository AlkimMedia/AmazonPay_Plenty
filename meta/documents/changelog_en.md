# Release notes for Amazon Pay

## 1.6.5
- no changes

## 1.6.4
- IPN validation
- considering unavailable articles

## 1.6.3
- capture only complete orders

## 1.6.2
- handle possible checkout errors
- support for CV2 Shopware plugin

## 1.6.1
- removed PaymentMethodBaseService

## 1.6.0

- Ceres Cookie Bar support
- Ceres 5 compatibility
- allow complete refund from sales order w/o needing a credit note
- minified css
- remove additional jQuery instance
- implement new PaymentMethodBaseService

## 1.5.5

- JS bug fix (login button)
- default container links

## 1.5.4

- oder status handling
- reduced capture amount
- debug mode for login button 

## 1.5.3

- improved JS handling for button
- optimized booting (thanks to @marcusschmidt)

## 1.5.2

### Fixed

- better recognition for access token in redirect

## 1.5.1

### Fixed

- better URL handling (language prefix)
- more reliable submission of order reference
- Shopware Connector - set order status

## 1.5.0

### Added

- optional: email address in shipping address

### Fixed

- IPN - removed adding closed authorisation transactions
- URL generation

## 1.4.1

### Fixed

- fatal error because of missing invoice address
- messy AJAX call stack
- updated dependencies

## 1.4.0

### Added

- multi factor authentication (PSD2)

## 1.3.1

### Added

- handling of external captures

### Fixed

- several IPN and transaction issues

## 1.3.0

### Added

- compatibility to Shopware Connector

### Fixed

- loss of access token from session

## 1.2.2

### Fixed

- net prices

## 1.2.1

### Fixed

- bug in capture event procedure 
- JS: scope for all widgets

## 1.2.0

### Added

- Shopware connector for transactions (test)

### Fixed

- button language
- label for buy button
- hide button for unavailable variants

## 1.1.6

### Gefixt

- order of JS execution to prevent click events from being removed by Vue

## 1.1.5

### Added

- event procedure for cancelling order 

### Fixed

- JS error (duplicate button)
- cleaner ogging
- version info and comment in Amazon calls

## 1.1.4

### Fixed

- duplicate button

## 1.1.3

### Added

- allowing to disable extensive Logging

### Fixed

- handling of transaction timeouts
- duplicate button

## 1.1.2

### Added

- minified JS and CSS

### Fixed

- Ceres language vars

## 1.1.1

### Added

- allowing to set an order status for authorized orders

### Fixed

- JS event issues in some templates with shipping method selection
- show nice error for rejected captures
- gtc/privacy checkbox in checkout
- compatibility with new trailing slash feature 

## 1.1.0

### Added

- Multi Currency Feature

### Fixed

- Email address and phone in customer record
- Checkout from article detail page with popup experience

## 1.0.4

### Fixed

- Authorization in popup
- JS/CSS no longer hosted on alkim.de

## 1.0.3

### Added

- Amazon Pay button on article detail page
- images and variants on checkout page

## 1.0.2

### Fixed

- compatibility Ceres 2.x

## 1.0.1

### Added

- separate house number from street

## 1.0.0

### Added

- Login functionality

## 0.1.11

### Fixed

- keeping up with plenty development

## Release 0.1.10
