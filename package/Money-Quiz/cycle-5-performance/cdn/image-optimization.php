<?php
/**
 * Money Quiz Plugin - Image Optimization
 * Worker 4: WebP Conversion and Lazy Loading
 * 
 * Implements advanced image optimization including WebP conversion,
 * responsive images, lazy loading, and CDN integration.
 * 
 * @package MoneyQuiz
 * @subpackage Performance\CDN
 * @since 5.0.0
 */

namespace MoneyQuiz\Performance\CDN;

/**
 * Image Optimization Class
 * 
 * Handles image optimization and delivery strategies
 */
class ImageOptimization {
    
    /**
     * Image optimization configuration
     * 
     * @var array
     */
    protected $config = array(
        'enabled' => true,
        'convert_webp' => true,
        'lazy_loading' => true,
        'responsive_images' => true,
        'compression_quality' => 85,
        'webp_quality' => 80,
        'lazy_load_offset' => 50,
        'exclude_classes' => array( 'no-lazy', 'skip-lazy' ),
        'preload_critical_images' => 3,
        'optimize_on_upload' => true,
        'serve_webp_to_supported' => true
    );
    
    /**
     * Supported image formats
     * 
     * @var array
     */
    protected $supported_formats = array( 'jpg', 'jpeg', 'png', 'gif' );
    
    /**
     * Image sizes
     * 
     * @var array
     */
    protected $image_sizes = array();
    
    /**
     * WebP support detection
     * 
     * @var bool|null
     */
    protected $webp_supported = null;
    
    /**
     * Performance metrics
     * 
     * @var array
     */
    protected $metrics = array(
        'images_optimized' => 0,
        'webp_conversions' => 0,
        'bytes_saved' => 0,
        'lazy_loaded' => 0
    );
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->load_config();
        $this->init();
    }
    
    /**
     * Initialize image optimization
     */
    protected function init() {
        if ( ! $this->config['enabled'] ) {
            return;
        }
        
        // Get WordPress image sizes
        $this->load_image_sizes();
        
        // Register hooks
        $this->register_hooks();
        
        // Detect WebP support
        $this->detect_webp_support();
    }
    
    /**
     * Load configuration
     */
    protected function load_config() {
        $saved_config = get_option( 'money_quiz_image_optimization_config', array() );
        $this->config = wp_parse_args( $saved_config, $this->config );
    }
    
    /**
     * Load image sizes
     */
    protected function load_image_sizes() {
        global $_wp_additional_image_sizes;
        
        $this->image_sizes = array();
        
        // Get default sizes
        foreach ( array( 'thumbnail', 'medium', 'medium_large', 'large' ) as $size ) {
            $this->image_sizes[ $size ] = array(
                'width' => get_option( $size . '_size_w' ),
                'height' => get_option( $size . '_size_h' ),
                'crop' => get_option( $size . '_crop', false )
            );
        }
        
        // Get additional sizes
        if ( ! empty( $_wp_additional_image_sizes ) ) {
            $this->image_sizes = array_merge( $this->image_sizes, $_wp_additional_image_sizes );
        }
        
        // Add Money Quiz specific sizes
        $this->image_sizes['money_quiz_featured'] = array(
            'width' => 800,
            'height' => 600,
            'crop' => true
        );
        
        $this->image_sizes['money_quiz_result'] = array(
            'width' => 600,
            'height' => 400,
            'crop' => true
        );
    }
    
    /**
     * Register hooks
     */
    protected function register_hooks() {
        // Image upload optimization
        if ( $this->config['optimize_on_upload'] ) {
            add_filter( 'wp_handle_upload', array( $this, 'optimize_uploaded_image' ) );
            add_filter( 'wp_generate_attachment_metadata', array( $this, 'optimize_image_sizes' ), 10, 2 );
        }
        
        // WebP conversion
        if ( $this->config['convert_webp'] ) {
            add_filter( 'wp_get_attachment_image_src', array( $this, 'maybe_serve_webp' ), 10, 3 );
            add_filter( 'wp_calculate_image_srcset', array( $this, 'add_webp_to_srcset' ), 10, 5 );
        }
        
        // Lazy loading
        if ( $this->config['lazy_loading'] ) {
            add_filter( 'wp_get_attachment_image_attributes', array( $this, 'add_lazy_loading_attributes' ), 10, 3 );
            add_filter( 'the_content', array( $this, 'add_lazy_loading_to_content' ), 99 );
            add_action( 'wp_footer', array( $this, 'add_lazy_loading_script' ) );
        }
        
        // Responsive images
        if ( $this->config['responsive_images'] ) {
            add_filter( 'wp_calculate_image_sizes', array( $this, 'optimize_image_sizes_attr' ), 10, 5 );
            add_filter( 'max_srcset_image_width', array( $this, 'increase_max_srcset_width' ) );
        }
        
        // Preload critical images
        add_action( 'wp_head', array( $this, 'preload_critical_images' ), 1 );
        
        // Add image sizes
        add_action( 'after_setup_theme', array( $this, 'register_image_sizes' ) );
        
        // Admin interface
        if ( is_admin() ) {
            add_filter( 'attachment_fields_to_edit', array( $this, 'add_optimization_info' ), 10, 2 );
        }
    }
    
    /**
     * Detect WebP support
     */
    protected function detect_webp_support() {
        // Check if explicitly set
        if ( isset( $_COOKIE['webp_supported'] ) ) {
            $this->webp_supported = $_COOKIE['webp_supported'] === '1';
            return;
        }
        
        // Check Accept header
        if ( isset( $_SERVER['HTTP_ACCEPT'] ) && strpos( $_SERVER['HTTP_ACCEPT'], 'image/webp' ) !== false ) {
            $this->webp_supported = true;
            return;
        }
        
        // Check User-Agent for known WebP support
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $webp_browsers = array(
            'Chrome' => 23,
            'Edge' => 18,
            'Firefox' => 65,
            'Safari' => 14
        );
        
        foreach ( $webp_browsers as $browser => $min_version ) {
            if ( preg_match( "/{$browser}\/(\d+)/i", $user_agent, $matches ) ) {
                if ( intval( $matches[1] ) >= $min_version ) {
                    $this->webp_supported = true;
                    return;
                }
            }
        }
        
        $this->webp_supported = false;
    }
    
    /**
     * Optimize uploaded image
     * 
     * @param array $upload Upload data
     * @return array Modified upload data
     */
    public function optimize_uploaded_image( $upload ) {
        if ( $upload['type'] && strpos( $upload['type'], 'image/' ) === 0 ) {
            $optimized = $this->optimize_image( $upload['file'] );
            
            if ( $optimized ) {
                $upload['file'] = $optimized['file'];
                $this->metrics['images_optimized']++;
                $this->metrics['bytes_saved'] += $optimized['saved'];
            }
        }
        
        return $upload;
    }
    
    /**
     * Optimize image sizes
     * 
     * @param array $metadata    Attachment metadata
     * @param int   $attachment_id Attachment ID
     * @return array Modified metadata
     */
    public function optimize_image_sizes( $metadata, $attachment_id ) {
        if ( ! isset( $metadata['sizes'] ) ) {
            return $metadata;
        }
        
        $upload_dir = wp_upload_dir();
        $base_dir = trailingslashit( $upload_dir['basedir'] ) . dirname( $metadata['file'] );
        
        // Optimize original
        $original_file = trailingslashit( $base_dir ) . basename( $metadata['file'] );
        $this->optimize_image( $original_file );
        
        // Create WebP version of original
        if ( $this->config['convert_webp'] ) {
            $this->create_webp_version( $original_file );
        }
        
        // Optimize each size
        foreach ( $metadata['sizes'] as $size => $data ) {
            $image_file = trailingslashit( $base_dir ) . $data['file'];
            
            // Optimize image
            $this->optimize_image( $image_file );
            
            // Create WebP version
            if ( $this->config['convert_webp'] ) {
                $webp_file = $this->create_webp_version( $image_file );
                
                if ( $webp_file ) {
                    $metadata['sizes'][ $size ]['sources']['webp'] = array(
                        'file' => basename( $webp_file ),
                        'filesize' => filesize( $webp_file )
                    );
                }
            }
        }
        
        return $metadata;
    }
    
    /**
     * Optimize image file
     * 
     * @param string $file Image file path
     * @return array|false Optimization result
     */
    protected function optimize_image( $file ) {
        if ( ! file_exists( $file ) ) {
            return false;
        }
        
        $original_size = filesize( $file );
        $image_type = exif_imagetype( $file );
        
        if ( ! $image_type ) {
            return false;
        }
        
        $result = false;
        
        switch ( $image_type ) {
            case IMAGETYPE_JPEG:
                $result = $this->optimize_jpeg( $file );
                break;
                
            case IMAGETYPE_PNG:
                $result = $this->optimize_png( $file );
                break;
                
            case IMAGETYPE_GIF:
                $result = $this->optimize_gif( $file );
                break;
        }
        
        if ( $result ) {
            $new_size = filesize( $file );
            return array(
                'file' => $file,
                'original_size' => $original_size,
                'new_size' => $new_size,
                'saved' => $original_size - $new_size
            );
        }
        
        return false;
    }
    
    /**
     * Optimize JPEG image
     * 
     * @param string $file File path
     * @return bool Success
     */
    protected function optimize_jpeg( $file ) {
        $image = imagecreatefromjpeg( $file );
        
        if ( ! $image ) {
            return false;
        }
        
        // Apply optimizations
        imageinterlace( $image, true ); // Progressive JPEG
        
        // Save optimized image
        $result = imagejpeg( $image, $file, $this->config['compression_quality'] );
        imagedestroy( $image );
        
        // Additional optimization with external tools if available
        if ( $this->is_jpegoptim_available() ) {
            $this->run_jpegoptim( $file );
        }
        
        return $result;
    }
    
    /**
     * Optimize PNG image
     * 
     * @param string $file File path
     * @return bool Success
     */
    protected function optimize_png( $file ) {
        $image = imagecreatefrompng( $file );
        
        if ( ! $image ) {
            return false;
        }
        
        // Enable alpha channel
        imagesavealpha( $image, true );
        
        // Save optimized image
        $compression_level = round( ( 100 - $this->config['compression_quality'] ) / 10 );
        $result = imagepng( $image, $file, $compression_level );
        imagedestroy( $image );
        
        // Additional optimization with external tools if available
        if ( $this->is_optipng_available() ) {
            $this->run_optipng( $file );
        }
        
        return $result;
    }
    
    /**
     * Optimize GIF image
     * 
     * @param string $file File path
     * @return bool Success
     */
    protected function optimize_gif( $file ) {
        // GIF optimization is limited with GD
        // Use external tools if available
        if ( $this->is_gifsicle_available() ) {
            return $this->run_gifsicle( $file );
        }
        
        return false;
    }
    
    /**
     * Create WebP version of image
     * 
     * @param string $file Original image file
     * @return string|false WebP file path or false
     */
    protected function create_webp_version( $file ) {
        if ( ! function_exists( 'imagewebp' ) ) {
            return false;
        }
        
        $image_type = exif_imagetype( $file );
        
        switch ( $image_type ) {
            case IMAGETYPE_JPEG:
                $image = imagecreatefromjpeg( $file );
                break;
                
            case IMAGETYPE_PNG:
                $image = imagecreatefrompng( $file );
                imagesavealpha( $image, true );
                break;
                
            case IMAGETYPE_GIF:
                $image = imagecreatefromgif( $file );
                break;
                
            default:
                return false;
        }
        
        if ( ! $image ) {
            return false;
        }
        
        $webp_file = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $file );
        
        // Create WebP
        $result = imagewebp( $image, $webp_file, $this->config['webp_quality'] );
        imagedestroy( $image );
        
        if ( $result ) {
            $this->metrics['webp_conversions']++;
            
            // Only keep WebP if it's smaller
            if ( filesize( $webp_file ) >= filesize( $file ) ) {
                unlink( $webp_file );
                return false;
            }
            
            return $webp_file;
        }
        
        return false;
    }
    
    /**
     * Maybe serve WebP version
     * 
     * @param array|false $image      Image data
     * @param int         $attachment_id Attachment ID
     * @param string|array $size      Image size
     * @return array Modified image data
     */
    public function maybe_serve_webp( $image, $attachment_id, $size ) {
        if ( ! $image || ! $this->webp_supported || ! $this->config['serve_webp_to_supported'] ) {
            return $image;
        }
        
        // Check if WebP version exists
        $webp_url = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $image[0] );
        $webp_path = $this->url_to_path( $webp_url );
        
        if ( file_exists( $webp_path ) ) {
            $image[0] = $webp_url;
        }
        
        return $image;
    }
    
    /**
     * Add WebP to srcset
     * 
     * @param array  $sources    Sources array
     * @param array  $size_array Size array
     * @param string $image_src  Image source
     * @param array  $image_meta Image metadata
     * @param int    $attachment_id Attachment ID
     * @return array Modified sources
     */
    public function add_webp_to_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
        if ( ! $this->webp_supported || ! $this->config['serve_webp_to_supported'] ) {
            return $sources;
        }
        
        foreach ( $sources as $width => &$source ) {
            $webp_url = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $source['url'] );
            $webp_path = $this->url_to_path( $webp_url );
            
            if ( file_exists( $webp_path ) ) {
                $source['url'] = $webp_url;
            }
        }
        
        return $sources;
    }
    
    /**
     * Add lazy loading attributes
     * 
     * @param array       $attr       Image attributes
     * @param WP_Post     $attachment Attachment object
     * @param string|array $size      Image size
     * @return array Modified attributes
     */
    public function add_lazy_loading_attributes( $attr, $attachment, $size ) {
        // Skip if already has loading attribute
        if ( isset( $attr['loading'] ) ) {
            return $attr;
        }
        
        // Skip excluded classes
        $class = $attr['class'] ?? '';
        foreach ( $this->config['exclude_classes'] as $exclude ) {
            if ( strpos( $class, $exclude ) !== false ) {
                return $attr;
            }
        }
        
        // Add lazy loading
        $attr['loading'] = 'lazy';
        $attr['data-src'] = $attr['src'];
        $attr['src'] = $this->get_placeholder_image( $size );
        $attr['class'] = trim( $class . ' money-quiz-lazy' );
        
        // Add srcset to data attribute
        if ( isset( $attr['srcset'] ) ) {
            $attr['data-srcset'] = $attr['srcset'];
            unset( $attr['srcset'] );
        }
        
        $this->metrics['lazy_loaded']++;
        
        return $attr;
    }
    
    /**
     * Add lazy loading to content images
     * 
     * @param string $content Content
     * @return string Modified content
     */
    public function add_lazy_loading_to_content( $content ) {
        if ( ! $content ) {
            return $content;
        }
        
        // Find all images
        preg_match_all( '/<img[^>]+>/i', $content, $matches );
        
        $image_count = 0;
        
        foreach ( $matches[0] as $image ) {
            $image_count++;
            
            // Skip first N images (above the fold)
            if ( $image_count <= $this->config['preload_critical_images'] ) {
                continue;
            }
            
            // Skip if already has loading attribute
            if ( strpos( $image, 'loading=' ) !== false ) {
                continue;
            }
            
            // Skip excluded classes
            $skip = false;
            foreach ( $this->config['exclude_classes'] as $exclude ) {
                if ( strpos( $image, $exclude ) !== false ) {
                    $skip = true;
                    break;
                }
            }
            
            if ( $skip ) {
                continue;
            }
            
            // Add lazy loading
            $lazy_image = $this->make_image_lazy( $image );
            $content = str_replace( $image, $lazy_image, $content );
        }
        
        return $content;
    }
    
    /**
     * Make image lazy
     * 
     * @param string $image Image HTML
     * @return string Modified image HTML
     */
    protected function make_image_lazy( $image ) {
        // Extract src
        preg_match( '/src=["\'](.*?)["\']/i', $image, $src_match );
        if ( empty( $src_match[1] ) ) {
            return $image;
        }
        
        $src = $src_match[1];
        
        // Add loading attribute
        $image = str_replace( '<img', '<img loading="lazy"', $image );
        
        // Move src to data-src
        $image = str_replace( 'src="' . $src . '"', 'data-src="' . $src . '"', $image );
        $image = str_replace( "src='" . $src . "'", "data-src='" . $src . "'", $image );
        
        // Add placeholder
        $placeholder = $this->get_placeholder_image();
        $image = preg_replace( '/<img/', '<img src="' . $placeholder . '"', $image, 1 );
        
        // Move srcset to data-srcset
        if ( preg_match( '/srcset=["\'](.*?)["\']/i', $image, $srcset_match ) ) {
            $image = str_replace( 
                'srcset="' . $srcset_match[1] . '"', 
                'data-srcset="' . $srcset_match[1] . '"', 
                $image 
            );
        }
        
        // Add lazy class
        if ( preg_match( '/class=["\'](.*?)["\']/i', $image, $class_match ) ) {
            $new_class = $class_match[1] . ' money-quiz-lazy';
            $image = str_replace( 
                'class="' . $class_match[1] . '"', 
                'class="' . $new_class . '"', 
                $image 
            );
        } else {
            $image = str_replace( '<img', '<img class="money-quiz-lazy"', $image );
        }
        
        $this->metrics['lazy_loaded']++;
        
        return $image;
    }
    
    /**
     * Get placeholder image
     * 
     * @param string|array $size Image size
     * @return string Placeholder URL
     */
    protected function get_placeholder_image( $size = 'thumbnail' ) {
        // Use transparent 1x1 GIF
        return 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';
    }
    
    /**
     * Add lazy loading script
     */
    public function add_lazy_loading_script() {
        ?>
        <script>
        (function() {
            var lazyImages = [].slice.call(document.querySelectorAll('.money-quiz-lazy'));
            
            if ('IntersectionObserver' in window) {
                var lazyImageObserver = new IntersectionObserver(function(entries, observer) {
                    entries.forEach(function(entry) {
                        if (entry.isIntersecting) {
                            var lazyImage = entry.target;
                            lazyImage.src = lazyImage.dataset.src;
                            
                            if (lazyImage.dataset.srcset) {
                                lazyImage.srcset = lazyImage.dataset.srcset;
                            }
                            
                            lazyImage.classList.remove('money-quiz-lazy');
                            lazyImageObserver.unobserve(lazyImage);
                        }
                    });
                }, {
                    rootMargin: '<?php echo esc_js( $this->config['lazy_load_offset'] ); ?>px'
                });
                
                lazyImages.forEach(function(lazyImage) {
                    lazyImageObserver.observe(lazyImage);
                });
            } else {
                // Fallback for older browsers
                var lazyLoad = function() {
                    var scrollTop = window.pageYOffset;
                    
                    lazyImages.forEach(function(lazyImage) {
                        if (lazyImage.offsetTop < (window.innerHeight + scrollTop + <?php echo esc_js( $this->config['lazy_load_offset'] ); ?>)) {
                            lazyImage.src = lazyImage.dataset.src;
                            
                            if (lazyImage.dataset.srcset) {
                                lazyImage.srcset = lazyImage.dataset.srcset;
                            }
                            
                            lazyImage.classList.remove('money-quiz-lazy');
                        }
                    });
                    
                    lazyImages = lazyImages.filter(function(image) {
                        return image.classList.contains('money-quiz-lazy');
                    });
                    
                    if (lazyImages.length === 0) {
                        document.removeEventListener('scroll', lazyLoad);
                        window.removeEventListener('resize', lazyLoad);
                        window.removeEventListener('orientationchange', lazyLoad);
                    }
                };
                
                document.addEventListener('scroll', lazyLoad);
                window.addEventListener('resize', lazyLoad);
                window.addEventListener('orientationchange', lazyLoad);
                lazyLoad();
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Preload critical images
     */
    public function preload_critical_images() {
        global $post;
        
        if ( ! $post ) {
            return;
        }
        
        // Get featured image
        if ( has_post_thumbnail( $post->ID ) ) {
            $image_id = get_post_thumbnail_id( $post->ID );
            $image_data = wp_get_attachment_image_src( $image_id, 'large' );
            
            if ( $image_data ) {
                echo sprintf(
                    '<link rel="preload" as="image" href="%s">' . "\n",
                    esc_url( $image_data[0] )
                );
            }
        }
        
        // Preload Money Quiz specific images
        if ( has_shortcode( $post->post_content, 'money_quiz' ) ) {
            $this->preload_quiz_images();
        }
    }
    
    /**
     * Preload quiz images
     */
    protected function preload_quiz_images() {
        // Preload quiz background or logo
        $quiz_logo = get_option( 'money_quiz_logo' );
        if ( $quiz_logo ) {
            echo sprintf(
                '<link rel="preload" as="image" href="%s">' . "\n",
                esc_url( $quiz_logo )
            );
        }
        
        // Preload archetype images
        global $wpdb;
        $archetypes = $wpdb->get_results(
            "SELECT image_url FROM {$wpdb->prefix}mq_archetypes WHERE is_active = 1 AND image_url IS NOT NULL LIMIT 3",
            ARRAY_A
        );
        
        foreach ( $archetypes as $archetype ) {
            if ( ! empty( $archetype['image_url'] ) ) {
                echo sprintf(
                    '<link rel="preload" as="image" href="%s">' . "\n",
                    esc_url( $archetype['image_url'] )
                );
            }
        }
    }
    
    /**
     * Register image sizes
     */
    public function register_image_sizes() {
        foreach ( $this->image_sizes as $name => $size ) {
            add_image_size( $name, $size['width'], $size['height'], $size['crop'] );
        }
    }
    
    /**
     * Optimize image sizes attribute
     * 
     * @param string $sizes      Sizes attribute value
     * @param array  $size       Image size
     * @param string $image_src  Image source
     * @param array  $image_meta Image metadata
     * @param int    $attachment_id Attachment ID
     * @return string Modified sizes attribute
     */
    public function optimize_image_sizes_attr( $sizes, $size, $image_src, $image_meta, $attachment_id ) {
        // Add Money Quiz specific breakpoints
        $sizes = '(max-width: 480px) 100vw, (max-width: 768px) 90vw, (max-width: 1024px) 80vw, 800px';
        
        return $sizes;
    }
    
    /**
     * Increase max srcset width
     * 
     * @param int $max_width Max width
     * @return int Modified max width
     */
    public function increase_max_srcset_width( $max_width ) {
        return 2560; // Support up to 2K displays
    }
    
    /**
     * Add optimization info to attachment
     * 
     * @param array   $form_fields Form fields
     * @param WP_Post $post        Attachment post
     * @return array Modified form fields
     */
    public function add_optimization_info( $form_fields, $post ) {
        if ( ! wp_attachment_is_image( $post->ID ) ) {
            return $form_fields;
        }
        
        $metadata = wp_get_attachment_metadata( $post->ID );
        $file_path = get_attached_file( $post->ID );
        $original_size = filesize( $file_path );
        
        // Check for WebP version
        $webp_path = preg_replace( '/\.(jpe?g|png|gif)$/i', '.webp', $file_path );
        $has_webp = file_exists( $webp_path );
        
        $info = '<div class="money-quiz-image-optimization">';
        $info .= '<strong>Optimization Status:</strong><br>';
        $info .= sprintf( 'Original Size: %s<br>', size_format( $original_size ) );
        
        if ( $has_webp ) {
            $webp_size = filesize( $webp_path );
            $savings = ( 1 - ( $webp_size / $original_size ) ) * 100;
            $info .= sprintf( 'WebP Size: %s (%.1f%% smaller)<br>', size_format( $webp_size ), $savings );
        }
        
        $info .= sprintf( 'Sizes Generated: %d<br>', count( $metadata['sizes'] ?? array() ) );
        $info .= '</div>';
        
        $form_fields['money_quiz_optimization'] = array(
            'label' => 'Money Quiz Optimization',
            'input' => 'html',
            'html' => $info
        );
        
        return $form_fields;
    }
    
    /**
     * Check if jpegoptim is available
     * 
     * @return bool
     */
    protected function is_jpegoptim_available() {
        static $available = null;
        
        if ( $available === null ) {
            exec( 'which jpegoptim 2>&1', $output, $return );
            $available = $return === 0;
        }
        
        return $available;
    }
    
    /**
     * Run jpegoptim
     * 
     * @param string $file File path
     * @return bool Success
     */
    protected function run_jpegoptim( $file ) {
        $command = sprintf(
            'jpegoptim --strip-all --max=%d %s 2>&1',
            $this->config['compression_quality'],
            escapeshellarg( $file )
        );
        
        exec( $command, $output, $return );
        
        return $return === 0;
    }
    
    /**
     * Check if optipng is available
     * 
     * @return bool
     */
    protected function is_optipng_available() {
        static $available = null;
        
        if ( $available === null ) {
            exec( 'which optipng 2>&1', $output, $return );
            $available = $return === 0;
        }
        
        return $available;
    }
    
    /**
     * Run optipng
     * 
     * @param string $file File path
     * @return bool Success
     */
    protected function run_optipng( $file ) {
        $level = round( ( 100 - $this->config['compression_quality'] ) / 14 ); // 0-7
        
        $command = sprintf(
            'optipng -o%d -strip all %s 2>&1',
            $level,
            escapeshellarg( $file )
        );
        
        exec( $command, $output, $return );
        
        return $return === 0;
    }
    
    /**
     * Check if gifsicle is available
     * 
     * @return bool
     */
    protected function is_gifsicle_available() {
        static $available = null;
        
        if ( $available === null ) {
            exec( 'which gifsicle 2>&1', $output, $return );
            $available = $return === 0;
        }
        
        return $available;
    }
    
    /**
     * Run gifsicle
     * 
     * @param string $file File path
     * @return bool Success
     */
    protected function run_gifsicle( $file ) {
        $command = sprintf(
            'gifsicle -O3 --batch %s 2>&1',
            escapeshellarg( $file )
        );
        
        exec( $command, $output, $return );
        
        return $return === 0;
    }
    
    /**
     * Convert URL to file path
     * 
     * @param string $url URL
     * @return string File path
     */
    protected function url_to_path( $url ) {
        $upload_dir = wp_upload_dir();
        
        if ( strpos( $url, $upload_dir['baseurl'] ) === 0 ) {
            return str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $url );
        }
        
        return str_replace( site_url(), ABSPATH, $url );
    }
    
    /**
     * Get optimization statistics
     * 
     * @return array Statistics
     */
    public function get_stats() {
        return array(
            'images_optimized' => $this->metrics['images_optimized'],
            'webp_conversions' => $this->metrics['webp_conversions'],
            'bytes_saved' => $this->metrics['bytes_saved'],
            'lazy_loaded' => $this->metrics['lazy_loaded'],
            'webp_supported' => $this->webp_supported ? 'Yes' : 'No',
            'average_savings' => $this->metrics['images_optimized'] > 0 
                ? round( $this->metrics['bytes_saved'] / $this->metrics['images_optimized'] ) 
                : 0
        );
    }
}

// Initialize image optimization
global $money_quiz_image_optimization;
$money_quiz_image_optimization = new ImageOptimization();