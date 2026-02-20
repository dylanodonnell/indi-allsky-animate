# indi-allsky-animate
Just a PHP page that will loop your allsky-indi remote images in a near-realtime loop.

Demo : https://byronbayobservatory.com.au/allsky/

## Installation

1. **Clone or download** this repository to your web server
2. **Place in FTP directory** - Add the files to the root directory where your allsky-indi FTP dumps images, or place elsewhere in your webserver's folder structure
3. **Configure BASEDIR** (optional) - If you're not using the root directory, define the `BASEDIR` constant to point to your image folder

## Configuration

### Basic Setup

If your images are in the default FTP location, no configuration is needed. Just drop the files in place.

### Custom Directory

To use a custom directory for your images, edit the configuration at the top of the PHP files:

```php
define('BASEDIR', '/path/to/your/images');
```

## Usage

Simply navigate to the index page in your browser:

```
http://your-domain.com/indi-allsky-animate/
```

The page will automatically:
- Detect available all-sky images
- Loop through them in sequence
- Update as new images arrive

## Requirements

- PHP 5.6 or higher
- A web server with PHP support (Apache, Nginx, etc.)
- FTP-synced all-sky images from INDI

## Project Details

- **Language:** PHP
- **License:** Public Domain
- **Related Project:** [allsky-indi](https://github.com/indilib/indi-allsky)

## Contributing

Feel free to submit issues and enhancement requests!

## Support

For issues or questions related to INDI and all-sky imaging, visit the [INDI GitHub organization](https://github.com/indilib/).