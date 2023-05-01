<h1 align="left">Imagic for Laravel Nova 4</h1>

![Imagic for Laravel Nova 4](https://ayvazyan.pro/imagic_banner.png)
<p align="left">
  Imagic is a Laravel Nova field package that allows for image manipulation capabilities, such as cropping, resizing, quality adjustment, and WebP conversion. It utilizes the powerful Intervention Image class for image manipulation. The purpose of this package is to optimize images for web usage by converting them to the WebP format, which provides superior compression and faster load times.
<br><br>Advanced Image Manipulation Made Easy with Images Magic
<br><br>✅ Multiple Uploads <br>✅ Cropping <br>✅ Resizing
<br>✅ Fitting <br>✅ Quality Control <br>✅ WebP Conversion
<br>✅ Watermarking
</p>

<script data-name="BMC-Widget" data-cfasync="false" src="https://cdnjs.buymeacoffee.com/1.0.0/widget.prod.min.js" data-id="ayvazyan403" data-description="Support me on Buy me a coffee!" data-message="" data-color="#40DCA5" data-position="Right" data-x_margin="18" data-y_margin="18"></script>

[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-Donate-yellow?style=for-the-badge&logo=buymeacoffee)](https://www.buymeacoffee.com/ayvazyan403)

![WEDO](https://wedo.design/logo-black.svg)

### Requirements

* PHP (^7.1 or higher)
* Laravel Nova (^4.0 or higher)

### 🚀 Installation
#### Install the package via Composer.
```` bash
composer require ayvazyan10/imagic
````
### 📚 Usage
Here is an example of how to use Imagic in your Laravel Nova application:
In your Laravel Nova resources, use the Imagic field:
```` php
use Ayvazyan10\Imagic\Imagic;

public function fields(Request $request)
{
    return [
        // ...

        Imagic::make('Image', 'image'),

        // ...
    ];
}
````
### ⚡ All Methods
```` php
Imagic::make('Image')
    ->multiple()
    ->crop($width, $height, $left = 0, $top = 0)
    ->resize($width, $height)
    ->fit($width, $height)
    ->quality($quality)
    ->convert($convert = true)
    ->watermark($path, $position = 'bottom-right', $x = 0, $y = 0);
````
### 📖 Examples
Below are some examples in different scenarios.
#### - <u>Multiple Images</u>
To enable multiple image uploads, use the multiple() method. Note that when you use the multiple() method, your database column should be of type text, longtext, or json to store all images in a JSON format. Additionally, you will have the ability to sort uploaded images by drag and drop.
``` php
Imagic::make('Images')->multiple(),
```
#### - <u>Cropping</u>
To crop images, use the crop() method:
``` php
Imagic::make('Image')->crop($width, $height, $left, $top),
```
#### - <u>Resizing</u>
To resize images, use the resize() method:
``` php
Imagic::make('Image')->resize($width = int|null, $height = int|null),
```
#### - <u>Quality</u>
To adjust the image quality, use the quality() method: default is 90
``` php
Imagic::make('Image')->quality(90),
```
#### - <u>WebP Conversion</u>
To convert images to WebP format, use the convert() method:<br>
By default, the images will be converted to WebP format. To disable conversion, pass false to the convert() method:
``` php
Imagic::make('Image')->convert(false),
```
#### - <u>Fit</u>
You can use the fit() method when defining the Imagic field in your Nova resource:
``` php
Imagic::make('Image')->fit($width, $height),
```
#### - <u>Field with watermark</u>
Replace the /path/to/watermark.png with the actual path to your watermark image.<br>
This will add the watermark to the image with the specified path, position, and offset (15x15 pixels from the bottom-right corner in this example).
Remember to import the Imagic class at the top of your Nova resource file:
``` php
Imagic::make('Image')->watermark('/path/to/watermark.png', 'bottom-right', 15, 15),
```

## Contributing

Please see [contributing.md](contributing.md) for details and a todolist.

## Security

If you discover any security related issues, please email ayvazyan403@gmail.com instead of using the issue tracker.

## Author

- <a href="https://github.com/ayvazyan10">Razmik Ayvazyan</a>

## License

MIT. Please see the [license file](license.md) for more information.
