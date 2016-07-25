# Enable SCSS CSS in WordPress

SCSS is an abstraction layer that adds some very powerful features to CSS. It
will speed up your development process and make your life that much easier. Find
out more from the links below and then head on back.

WP-SCSS is an add-on is for theme creators, and allows you to build a site using SCSS, and to use variables from
the customizer that are live updated. It also allows you to pass arrays and have them converted to SCSS maps.

## Installation:

WP-SCSS is installed via composer.

Simply add the following line to your composer.json file's `require` section :`"trampoline-digital/wp-scss": "dev-master"`

Then run `composer update`;

At the top of your `functions.php` file, add the following line : `require_once(__DIR__ . '/vendor/wp-scss/wp-scss.php' );`

You are now ready to use WP-SCSS

## Usage:

To load an SCSS file, simply enqueue it the way you would any normal stylesheet.

    `wp_enqueue_style('scss_styles', get_template_directory_uri() . '/assets/scss/scss_styles.scss');`

You won't need a link to your main style sheet in `header.php`. Just make sure
that `wp_head()` is called in the document head.

All the standard SCSS features are supported as well as `@import` rules anywhere
within the file.

### Passing in variables from PHP

You can pass variables into your `.scss` files using the `scss_vars` hook or with the
functions defined in the PHP Interface section:

This is an example of how to add a single variable.

```
add_filter( 'scss_vars', 'scss_color', 10, 2 );
    // $handle is a reference to the handle used with wp_enqueue_style() - don't take it out
    function scss_color( $vars, $handle ) {
    $vars["blue"] = "lightblue";
    return $vars;
}
```

This is an example of how to add an array of single variables that are set using the customizer with Kirki.
```
add_filter( 'scss_vars', 'scss_colors', 10, 2 );
// $handle is a reference to the handle used with wp_enqueue_style() - don't take it out
function scss_colors( $vars, $handle ) {
    $defaults = new CustomizerDefaults();
    $colors = $defaults->get_colors();
    foreach ($defaults->get_colors() as $color){
        $slug = $color['slug'];
        $vars[$slug] = get_theme_mod($slug, $colors[$slug]['value']);
    }
    return $vars;
}
```

If you would like to use an SCSS map, just pass though an array in the following format:

```
add_filter( 'scss_vars', 'scss_map', 10, 2 );
 // $handle is a reference to the handle used with wp_enqueue_style() - don't take it out
function scss_color( $vars, $handle ) {
    $vars["array_of_colors"] = array(
        "blue" = "darkblue",
        "gray" = "lightgray" 
    );
    return $vars;
}
```
 
You can access the values in SCSS the following way:

```
.heading{
    color: map-get(array_of_colors, blue); // Will return the value of 'blue' ('darkblue')
    background-color: map-get(array_of_colors, gray); // Will return the value of 'gray' ('darkgray')
    
}
```


### Default variables

*There is a default variables* you can use without worrying about the above code:

**`$theme-url`** is the URL of the current theme directory:

It is important to use this because you can't use relative paths - the compiled CSS is
stored in the uploads folder as it is the only place you can guarantee being
able to write to in any given WordPress installation. As a result relative URLs will
break.


### TODO
- Test adding SCSS functions
- Write unit tests
- To some intense QA

## Further Reading

[Read the DCDD.js documentation here](http://sass-lang.com/guide).

Read the documentation [specific to the PHP parser here](http://leafo.github.io/scssphp/).


## Contributors
This project is a revival (started with a duplication) of Robert O'Rourke's [WP-LESS](https://github.com/roborourke/wp-less)

It goes without saying that this project would never have been possible without the hard work of [Robert O'Rourke](https://github.com/roborourke)

Alos, big massive thanks to those whose contributions and discussion helped to build the original WP-LESS plugin.

* [Tom Willmot](https://github.com/willmot)
* [Franz Josef Kaiser](https://github.com/franz-josef-kaiser)
* [Rarst](https://github.com/rarst)

## License

The software is licensed under the [MIT Licence](http://www.opensource.org/licenses/mit-license.php).
