<?php
/*
Plugin Name: Global Prayer Times (Shortcode Attributes)
Description: Display prayer times based on provided city and country attributes with live countdown and elegant design. i.e [prayer_times city="Makkah" country="Saudi Arabia"]
Version: 1.2
Author: Awais Yaqoob
*/

// Enqueue Scripts and Styles
function gpt_enqueue_scripts() {
    if ( ! is_admin() ) {
        wp_enqueue_script('jquery');
        wp_enqueue_style('gpt-style', plugins_url('assets/style.css', __FILE__));
       // wp_enqueue_script('gpt-script', plugins_url('assets/script.js', __FILE__), array('jquery'), null, true);
        //wp_localize_script('gpt-script', 'ajax_obj', array(
          //  'ajax_url' => admin_url('admin-ajax.php')
       // ));
    }
}
add_action('wp_enqueue_scripts', 'gpt_enqueue_scripts');

// Shortcode: [prayer_times city="Makkah" country="Saudi Arabia"]
function gpt_prayer_times_shortcode( $atts ) {
    $atts = shortcode_atts(
        array(
            'city'    => '',
            'country' => '',
        ),
        $atts,
        'prayer_times'
    );
    
    if ( empty( $atts['city'] ) || empty( $atts['country'] ) ) {
        return '<p>Please provide both city and country in the shortcode.</p>';
    }
    
    $city    = sanitize_text_field( $atts['city'] );
    $country = sanitize_text_field( $atts['country'] );
    
    // Fetch prayer times from Aladhan API
    $api_url = "https://api.aladhan.com/v1/timingsByCity?city=" . urlencode( $city ) . "&country=" . urlencode( $country ) . "&method=2";
    $response = wp_remote_get( $api_url );
    
    if ( is_wp_error( $response ) ) {
        return "<p>Failed to fetch prayer times. Please try again later.</p>";
    }
    
    $body    = json_decode( wp_remote_retrieve_body( $response ), true );
    $timings = $body['data']['timings'] ?? array();
    
    if ( empty( $timings ) ) {
        return "<p>No data found for <strong>" . esc_html( $city ) . ", " . esc_html( $country ) . "</strong>. Please check the names.</p>";
    }
    
    // Set timezone if available
    if ( ! empty( $body['data']['meta']['timezone'] ) ) {
        date_default_timezone_set( $body['data']['meta']['timezone'] );
    }
    
    $current_time = time(); // Current timestamp in seconds
    $next_prayer = null;
    $next_prayer_timestamp = null;
    
    // Determine next prayer (Fajr, Dhuhr, Asr, Maghrib, Isha)
    foreach ( array( 'Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ) as $prayer ) {
        $prayer_time_str = date( "Y-m-d" ) . ' ' . $timings[ $prayer ];
        $prayer_timestamp = strtotime( $prayer_time_str );
        if ( $prayer_timestamp > $current_time ) {
            $next_prayer = $prayer;
            $next_prayer_timestamp = $prayer_timestamp;
            break;
        }
    }
    if ( ! $next_prayer ) {
        // No prayer remains today; assume next day's Fajr
        $next_prayer = 'Fajr';
        $next_prayer_timestamp = strtotime( date( "Y-m-d", strtotime('+1 day') ) . ' ' . $timings['Fajr'] );
    }
    $remaining_seconds = $next_prayer_timestamp - $current_time;
    ?>
    <div class="gpt-container">
        <h2>Today's Prayer Times in <?php echo esc_html( $city ); ?>, <?php echo esc_html( $country ); ?></h2>
<h3 style="text-align: center; font-size: 16px; color: #666;">
    Prayer times today, <strong><?php echo date('l, F j, Y'); ?></strong>, 
    corresponding to <strong><?php echo esc_html( $body['data']['date']['hijri']['day'] . ' ' . $body['data']['date']['hijri']['month']['en'] . ', ' . $body['data']['date']['hijri']['year'] . ' AH' ); ?></strong>.
</h3>
<br>

        <div class="gpt-next-prayer-wrapper" style="display: flex; justify-content: center; gap: 20px; margin-bottom: 20px;"> 
            <div class="gpt-next-prayer-box" style="background-color: #d4edda; color: #155724; padding: 10px 20px; border-radius: 5px; font-size: 18px;">
				Next Prayer: <strong><?php echo esc_html( $next_prayer ); ?></strong>
			</div>
			<div class="gpt-countdown-box" style="background-color: #ffe5b4; color: #d35400; padding: 10px 20px; border-radius: 5px; font-size: 18px;">
				Starts in: <b><span class="gpt-countdown" data-remaining="<?php echo esc_attr( $remaining_seconds ); ?>">--:--:--</span></b>
			</div>
		</div>
		<div class="gpt-table-container">
			<table class="gpt-table">
				<thead>
					<tr>
						<th>Prayer</th>
						<th>Time</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array( 'Fajr', 'Dhuhr', 'Asr', 'Maghrib', 'Isha' ) as $prayer ) : ?>
					<tr>
						<td><?php echo esc_html( $prayer ); ?></td>
						<td><?php echo esc_html( $timings[ $prayer ] ?? 'N/A' ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<div class="gpt-sun-info" style="display: flex; justify-content: space-between; gap: 20px; margin-top: 20px; margin-bottom: 50px;">
			<div class="gpt-sunrise-box" style="background-color:rgb(235, 235, 235); color:rgb(0, 0, 0); padding: 10px 20px; border-radius: 5px; display: flex; flex-direction:column; align-items: center; gap: 10px; width: 48%;">
            <img src="<?php echo plugins_url('assets/sunrise.png', __FILE__); ?>" alt="Sunrise" style="width:50px; height:50px;">				
				<span style="font-size: 20px;"> Sunrise time in <?php echo esc_html( $city ); ?></span><b><span style="font-size: 20px;">  <?php echo esc_html( $timings['Sunrise'] ?? 'N/A' );?> </span></b>
			</div>
			<div class="gpt-sunset-box" style="background-color: rgb(235, 235, 235); color:rgb(0, 0, 0); padding: 10px 20px; border-radius: 5px; display: flex; flex-direction:column; align-items: center; gap: 10px; width: 48%;">
            <img src="<?php echo plugins_url('assets/sunset.png', __FILE__); ?>" alt="Sunset" style="width:50px; height:50px;">
				<span style="font-size: 20px;"> Sunset time in <?php echo esc_html( $city ); ?> </span><b><span style="font-size: 20px;"> <?php echo esc_html( $timings['Sunset'] ?? 'N/A' );?> </span></b>
			</div>
		</div>

<script>
	jQuery(document).ready(function($){
		function updateCountdown() {
			var countdownElem = $('.gpt-countdown');
			var remaining = parseInt(countdownElem.data('remaining'));
			if (remaining > 0) {
				var hrs = Math.floor(remaining / 3600);
				var mins = Math.floor((remaining % 3600) / 60);
				var secs = remaining % 60;
				countdownElem.text((hrs < 10 ? '0' + hrs : hrs) + ':' + (mins < 10 ? '0' + mins : mins) + ':' + (secs < 10 ? '0' + secs : secs));
				countdownElem.data('remaining', remaining - 1);
			} else {
				countdownElem.text('00:00:00');
			}
		}
		setInterval(updateCountdown, 1000);
	});
</script>
 <?php
// Fetch monthly prayer times from Aladhan API
$monthly_api_url = "https://api.aladhan.com/v1/calendarByCity?city=" . urlencode( $city ) . "&country=" . urlencode( $country ) . "&method=2&month=" . date('m') . "&year=" . date('Y');
$monthly_response = wp_remote_get( $monthly_api_url );

if ( ! is_wp_error( $monthly_response ) ) {
    $monthly_body = json_decode( wp_remote_retrieve_body( $monthly_response ), true );

    if ( isset( $monthly_body['data'] ) ) {
        echo '<h2>Monthly Prayer Times in ' . esc_html( $city ) . ", " . esc_html( $country ) .'</h2>';
        echo '<div class="gpt-table-container">';
        echo '<table class="gpt-table">';
        echo '<thead><tr><th>Date</th><th>Fajr</th><th>Dhuhr</th><th>Asr</th><th>Maghrib</th><th>Isha</th></tr></thead>';
        echo '<tbody>';

        $today = date('d-m-Y'); // Current date in the same format as API response

		function clean_time($time) {
            return preg_replace('/\s*\(.*?\)/', '', $time); // Remove (PKT) or any other text in parentheses
        }
		
        foreach ( $monthly_body['data'] as $day ) {
            $date = $day['date']['gregorian']['date']; // API format is d-m-Y

            // Add class "highlight" if it's today's date
            $highlight_class = ($date === $today) ? 'class="highlight-gpt"' : '';

			 echo '<tr ' . $highlight_class . '>';
            echo '<td>' . esc_html( $date ) . '</td>';
            echo '<td>' . esc_html( clean_time($day['timings']['Fajr']) ) . '</td>';
            echo '<td>' . esc_html( clean_time($day['timings']['Dhuhr']) ) . '</td>';
            echo '<td>' . esc_html( clean_time($day['timings']['Asr']) ) . '</td>';
            echo '<td>' . esc_html( clean_time($day['timings']['Maghrib']) ) . '</td>';
            echo '<td>' . esc_html( clean_time($day['timings']['Isha']) ) . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }
}
?>
    </div>
    <?php
	return ob_get_clean();
}
add_shortcode('prayer_times', 'gpt_prayer_times_shortcode');
?>
