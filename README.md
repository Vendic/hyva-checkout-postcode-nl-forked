## Trinos - Postcode.nl Magento 2 module

A Vendic fork for `trinos-nl/magento2-postcode-nl` module.
It has several improvements over the original module.

## Installation

2. Require the `vendic/hyva-checkout-postcode-nl-forked` package:
    ```sh
    composer require vendic/hyva-checkout-postcode-nl-forked
    ```

3. Run `bin/magento setup:upgrade`

## Additional steps

After installing the module, there are some additional steps you need to do.

### 1. Configure the module

Go to `Stores > Configuration > Sales > Postcode.nl Api` and configure the module.
Enable the module and fill in your API key.

### 2. Checkout configuration

Go to `Stores > Configuration > Hyvä Themes > Checkout` and configure `postcodenl_manual_mode` field.
This field is responsible for the manual mode checkbox of the postcode check.

Our recommendation is to set this fields to the following order and configuration:

| Attribute              | Enabled | Required | Length |
|------------------------|---------|----------|--------|
| country_id             | Yes     | Yes      | 100%   |
| postcode               | Yes     | Yes      | 50%    |
| street.1               | Yes     | Yes      | 25%    |
| street.2               | Yes     | No       | 25%    |
| postcodenl_manual_mode | Yes     | No       | 100%   |
| street.0               | Yes     | Yes      | 100%   |
| city                   | Yes     | Yes      | 100%   |

## Fixes

As stated above, this module has several fixes compared to the original module

### Preventing validation when `housenumber` empty

When there is either `housenumber` or `postcode` fields empty,
we don't have to run postcode validation. So we're checking values to be not empty before running the original validation
