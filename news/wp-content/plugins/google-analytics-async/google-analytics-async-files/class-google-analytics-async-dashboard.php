<?php
/*  Copyright Maniu, Carson McDonald */
include_once 'externals/OAuth.php';

class Google_Analytics_Async_Dashboard {

    var $text_domain;
    var $plugin_url;
    var $required_capability;

    var $ready;
    var $oauth_token;
    var $oauth_secret;
    var $profile_id = 0;
    var $post;
    var $stats_source = 0;

    var $base_url = 'https://www.googleapis.com/analytics/v2.4/';
    var $account_base_url = 'https://www.googleapis.com/analytics/v2.4/management/';
    var $account_base_url_new = 'https://www.googleapis.com/analytics/v3/management/';

    var $http_code;
    var $error = false;
    var $cache_timeout = 86400;
    var $cache_timeout_personal = 10800;
    var $cache_name = '';
    var $cache = 0;
    var $load_mode = 'soft';
    var $is_network_admin = 0;

    var $date_range;
    var $start_date;
    var $end_date;
    var $type = 0;
    var $filter = array();

    function __construct() {
        global $google_analytics_async;

        $this->text_domain = $google_analytics_async->text_domain;
        $this->plugin_url = $google_analytics_async->plugin_url;

        //required capability
        $this->required_capability = (!empty($google_analytics_async->current_settings['track_settings']['minimum_capability_reports'])) ? $google_analytics_async->current_settings['track_settings']['minimum_capability_reports'] : ((!empty($google_analytics_async->current_settings['track_settings']['minimum_role_capability_reports'])) ? $google_analytics_async->current_settings['track_settings']['minimum_role_capability_reports']: 'manage_options');

        //setup correct google analytics data source
        $this->is_network_admin = (is_network_admin() || (defined('DOING_AJAX') && isset($_POST['network_admin']) && $_POST['network_admin'])) ? 1 : 0;

        if(!$this->is_network_admin && isset($google_analytics_async->settings['google_login']['logged_in']) && isset($google_analytics_async->settings['track_settings']['google_analytics_account_id']) && $google_analytics_async->settings['track_settings']['google_analytics_account_id'])
            $this->stats_source = 'site';
        elseif(isset($google_analytics_async->network_settings['google_login']['logged_in']) && isset($google_analytics_async->network_settings['track_settings']['google_analytics_account_id']) && $google_analytics_async->network_settings['track_settings']['google_analytics_account_id'])
            $this->stats_source = 'network';


        add_action('admin_init', array($this, 'admin_init_handle_google_login' ));
        if($this->stats_source) {
            add_action('admin_init', array($this, 'admin_init'));
            add_action('admin_menu', array($this, 'admin_menu'));
            add_action('network_admin_menu', array($this, 'network_admin_menu'));
        }
    }

    function admin_init() {
        global $google_analytics_async, $pagenow;

        //load only for: dashboard, post type page and correct ajax call
         if(
            current_user_can($this->required_capability) &&
            (
                ($pagenow == 'index.php' && !isset($_GET['page'])) ||
                ($pagenow == 'index.php' && isset($_GET['page']) && empty($_GET['page'])) ||
                ($pagenow == 'index.php' && isset($_GET['page']) && $_GET['page'] == 'google-analytics-statistics') ||
                ($pagenow == 'post.php' && isset($_GET['post'])) ||
                (isset($_POST['action']) && $_POST['action'] == 'load_google_analytics')
            )
        ) {
			//filter variables
            $this->cache_timeout = apply_filters('ga_cache_timeout', $this->cache_timeout);
            $this->cache_timeout_personal = apply_filters('ga_cache_timeout_personal', $this->cache_timeout);

            //setup correct google analytics profile id
            if($this->stats_source == 'site') {
                $this->profile_id = $google_analytics_async->settings['track_settings']['google_analytics_account_id'];

                $this->oauth_token = $google_analytics_async->settings['google_login']['token'];
                $this->oauth_secret = $google_analytics_async->settings['google_login']['token_secret'];

                //change cache timeout for site based stats
                $this->cache_timeout = $this->cache_timeout_personal;
            }
            elseif($this->stats_source == 'network') {
                $this->profile_id = $google_analytics_async->network_settings['track_settings']['google_analytics_account_id'];

                $this->oauth_token = $google_analytics_async->network_settings['google_login']['token'];
                $this->oauth_secret = $google_analytics_async->network_settings['google_login']['token_secret'];

                //set up filters to show correct stats for current site
                if(!$this->is_network_admin) {
                    $url = function_exists('domain_mapping_siteurl') ? domain_mapping_siteurl(home_url()) : home_url();

                    $site_url_parts = explode('/', str_replace('http://', '', str_replace('https://', '', $url)));
                    if(!$site_url_parts)
                        $site_url_parts = explode('/', str_replace('http://', '', str_replace('https://', '', site_url())));

                    $this->filter[] = 'ga:hostname=='.$site_url_parts[0];

                    //if its in subdirectory mode, then set correct beggining for page path
                    if(count($site_url_parts) > 1) {
                        unset($site_url_parts[0]);
                        $pagepath = implode('/', $site_url_parts);

                        $this->filter[] = 'ga:pagePath=~^/'.$pagepath;
                    }
                }
            }

            if($this->profile_id && $this->oauth_token && $this->oauth_secret) {
                //set up date related variables needed to get data
                $this->date_range =
                (isset($_GET['date_range']) && ($_GET['date_range'] == 3 || $_GET['date_range'] == 12)) ? $_GET['date_range'] :
                ((isset($_POST['date_range']) && ($_POST['date_range'] == 3 || $_POST['date_range'] == 12)) ? $_POST['date_range'] : 1);

                $start_date = time() - (60 * 60 * 24 * 30 * $this->date_range);
                $this->start_date = date('Y-m-d', $start_date);
                $this->end_date = date('Y-m-d');

                //configure filter for posts to display proper data
                $this->post = (isset($_GET['post']) && $_GET['post']) ? $_GET['post'] : ((isset($_POST['post']) && $_POST['post']) ? $_POST['post'] : 0);
                if($this->post)
                    $this->filter[] = 'ga:pagePath=~/'.basename(get_permalink($this->post)).'/$';

                //configure type to know what kind of data should be loaded
                if(isset($_POST['action']) && $_POST['action'] == 'load_google_analytics' && isset($_POST['type']))
                    $this->type = $_POST['type'];
                elseif($pagenow == 'index.php' && !isset($_GET['page']))
                    $this->type = 'widget';
                elseif($pagenow == 'index.php' && isset($_GET['page']) && $_GET['page'] == 'google-analytics-statistics')
                    $this->type = 'statistics_page';
                elseif($this->post)
                    $this->type = 'post';

                //set up correct/unique for stats cache name
                $this->cache_name = 'gac32_'.$this->profile_id.get_current_blog_id().$this->is_network_admin.$this->start_date.$this->end_date.$this->post;

                //if its a ajax call, we dont want cached version
                if(!defined('DOING_AJAX'))
                    $this->cache = get_transient($this->cache_name);

                //unset cache if it needs to get more data
                if($this->type != 'widget' && count($this->cache) == 1)
                    $this->cache = 0;

                add_action( 'admin_enqueue_scripts', array( &$this, 'admin_enqueue_scripts' ) );

                //add all the widgets for dashboards and posts
                add_action('wp_dashboard_setup', array(&$this, 'register_google_analytics_dashboard_widget'));
                add_action('wp_network_dashboard_setup', array(&$this, 'register_google_analytics_dashboard_widget'));
                add_action('add_meta_boxes', array(&$this, 'register_google_analytics_post_widget'));

                //ajax is needed only if there is no cached version
                add_action('wp_ajax_load_google_analytics', array(&$this, 'load_google_analytics'));
            }
        }
    }

    function admin_enqueue_scripts($hook) {
        wp_register_script('google_charts_api', 'https://www.google.com/jsapi');
        wp_enqueue_script('google_charts_api');

        wp_register_script('google_analytics_async', $this->plugin_url . 'google-analytics-async-files/ga-async.js', array('jquery','sack', 'google_charts_api'), 325);
        wp_enqueue_script('google_analytics_async');
        //configure parameters for JS
        $params = array();
        if(is_network_admin())
            $params['network_admin'] = 1;
        if($this->post)
            $params['post'] = $this->post;
        if(isset($this->cache['chart_visitors']))
            $params['chart_visitors'] = $this->cache['chart_visitors'];
        if($this->type == 'statistics_page' && isset($this->cache['chart_countries']))
            $params['chart_countries'] = $this->cache['chart_countries'];
        $params['type'] = $this->type;
        $params['date_range'] = $this->date_range;
        $params['chart_visitors_title'] = ($this->date_range == 12) ? __('Month', $this->text_domain) : (($this->date_range == 3) ? __('Week', $this->text_domain) : __('Day', $this->text_domain));
        $params['load_mode'] = $this->load_mode;
        wp_localize_script( 'google_analytics_async', 'ga', $params );

        wp_register_style( 'GoogleAnalyticsAsyncStyle', $this->plugin_url . 'google-analytics-async-files/ga-async.css', array(), 38);
        wp_enqueue_style( 'GoogleAnalyticsAsyncStyle' );
    }

    function admin_menu() {
        global $google_analytics_async;

        if(
            !current_user_can($this->required_capability) ||
            (
                $this->stats_source == 'network'
                && !is_super_admin()
                && !empty($google_analytics_async->network_settings['track_settings']['supporter_only_reports'])
                && function_exists('is_pro_site')
                && !is_pro_site(get_current_blog_id(), $google_analytics_async->network_settings['track_settings']['supporter_only_reports'])
            )
        )
            return;

        add_dashboard_page(__('Statistics', $this->text_domain), __('Statistics', $this->text_domain), $this->required_capability, 'google-analytics-statistics', array( $this, 'google_analytics_statistics_page' ) );
    }

    function network_admin_menu() {
        add_dashboard_page(__('Statistics', $this->text_domain), __('Statistics', $this->text_domain), $this->required_capability, 'google-analytics-statistics', array( $this, 'google_analytics_statistics_page' ) );
    }

    function register_google_analytics_dashboard_widget() {
        global $google_analytics_async;

        if(
            !current_user_can($this->required_capability) ||
            (
                $this->stats_source == 'network'
                && !is_super_admin()
                && !empty( $google_analytics_async->network_settings['track_settings']['supporter_only_reports'] )
                && function_exists('is_pro_site')
                && !is_pro_site(get_current_blog_id(), $google_analytics_async->network_settings['track_settings']['supporter_only_reports'])
            )
        )
            return;

        wp_add_dashboard_widget('google_analytics_dashboard', __('Statistics - Last 30 Days', $this->text_domain), array(&$this, 'google_analytics_widget'));
    }

    function register_google_analytics_post_widget() {
        global $google_analytics_async;

        if(
            !current_user_can($this->required_capability) ||
            (
                $this->stats_source == 'network'
                && !is_super_admin()
                && !empty( $google_analytics_async->network_settings['track_settings']['supporter_only_reports'] )
                && function_exists('is_pro_site')
                && !is_pro_site(get_current_blog_id(), $google_analytics_async->network_settings['track_settings']['supporter_only_reports'])
            )
        )
            return;

        $screens = array( 'post', 'page' );

        foreach ( $screens as $screen )
            add_meta_box('google_analytics_dashboard', __('Statistics - Last 30 Days', $this->text_domain), array(&$this, 'google_analytics_widget'), $screen, 'normal');
    }

    function admin_init_handle_google_login() {
        global $google_analytics_async;
        $is_network = is_network_admin() ? 'network' : '';
        $redirect_url = $is_network ? admin_url('/network/settings.php') : admin_url('/options-general.php');

        //handle google login process
        if( isset($_REQUEST['google_login']) && $_REQUEST['google_login'] == 1 ) {
            if(($is_network && !is_super_admin()) || !current_user_can('manage_options'))
                die(__('Cheatin&#8217; uh?'));

            $google_analytics_async->save_options(array('google_login_temp' => array()), $is_network);

            $parameters = array(
                'oauth_callback' => add_query_arg(array('page' => 'google-analytics', 'google_login_return' => 'true'), $redirect_url),
                'scope' => 'https://www.googleapis.com/auth/analytics.readonly',
                'xoauth_displayname' => 'Google Analytics'
            );

            $method = new Google_Analytics_OAuthSignatureMethod_HMAC_SHA1();
            $consumer = new Google_Analytics_OAuthConsumer('anonymous', 'anonymous', NULL);
            $request = Google_Analytics_OAuthRequest::from_consumer_and_token($consumer, NULL, 'GET', 'https://www.google.com/accounts/OAuthGetRequestToken', $parameters);
            $request->sign_request($method, $consumer, NULL);

            $response = wp_remote_get($request->to_url(), array('sslverify' => false));
            if(is_wp_error($response)) {
                wp_redirect(add_query_arg(array('page' => 'google-analytics', 'dmsg' => urlencode($response->get_error_message()))));
                exit();
            }
            else{
                $response_code = wp_remote_retrieve_response_code( $response );
                $response_body = wp_remote_retrieve_body($response);

                if($response_code == 200) {
                    parse_str($response_body, $access_parameters);

                    $google_analytics_async->save_options(array('google_login_temp' => array('token' => $access_parameters['oauth_token'], 'token_secret' => $access_parameters['oauth_token_secret'])), $is_network);

                    wp_redirect(add_query_arg('oauth_token', urlencode($access_parameters['oauth_token']), 'https://www.google.com/accounts/OAuthAuthorizeToken?oauth_token'));
                }
                else
                    wp_redirect(add_query_arg(array('page' => 'google-analytics', 'dmsg' => urlencode($response_body)), $redirect_url));

                exit();
            }
        }
        else if( isset($_REQUEST['google_login_return']) ) {
            if(($is_network && !is_super_admin()) || !current_user_can('manage_options'))
                die(__('Cheatin&#8217; uh?'));

            $google_login_temp = $google_analytics_async->current_settings['google_login_temp'];

            $parameters = array('oauth_verifier' => $_REQUEST['oauth_verifier']);

            $method = new Google_Analytics_OAuthSignatureMethod_HMAC_SHA1();
            $consumer = new Google_Analytics_OAuthConsumer('anonymous', 'anonymous', NULL);
            $upgrade_token = new Google_Analytics_OAuthConsumer($google_login_temp['token'], $google_login_temp['token_secret']);
            $request = Google_Analytics_OAuthRequest::from_consumer_and_token($consumer, $upgrade_token, 'GET', 'https://www.google.com/accounts/OAuthGetAccessToken', $parameters);
            $request->sign_request($method, $consumer, $upgrade_token);

            $response = wp_remote_get($request->to_url(), array('sslverify' => false));
            if(is_wp_error($response)) {
                wp_redirect(add_query_arg(array('page' => 'google-analytics', 'dmsg' => urlencode($response->get_error_message()), $redirect_url)));
                exit();
            }
            else{
                $response_code = wp_remote_retrieve_response_code( $response );
                $response_body = wp_remote_retrieve_body($response);

                $google_analytics_async->save_options(array('google_login_temp' => array()), $is_network);

                if($response_code == 200) {
                    parse_str($response_body, $access_parameters);

                    $google_analytics_async->save_options(array('google_login' => array('token' => $access_parameters['oauth_token'], 'token_secret' => $access_parameters['oauth_token_secret'], 'logged_in' => 1)), $is_network);

                    wp_redirect(add_query_arg(array('page' => 'google-analytics', 'dmsg' => urlencode(__( 'Login successful!', $this->text_domain ))), $redirect_url));
                }
                else
                    wp_redirect(add_query_arg(array('page' => 'google-analytics', 'dmsg' => urlencode($response_body)), $redirect_url));

                exit();
            }
        }
        elseif( isset($_REQUEST['google_logout']) && $_REQUEST['google_logout'] == 1 ) {
            if(($is_network && !is_super_admin()) || !current_user_can('manage_options'))
                die(__('Cheatin&#8217; uh?'));

            $google_analytics_async->save_options(array('google_login' => array()), $is_network);

            wp_redirect(add_query_arg(array('page' => 'google-analytics', 'dmsg' => urlencode(__( 'Logout successful!', $this->text_domain ))), $redirect_url));
            exit();
        }
    }

    function prepare_authentication_header($url) {
        $signature_method = new Google_Analytics_OAuthSignatureMethod_HMAC_SHA1();
        $consumer = new Google_Analytics_OAuthConsumer('anonymous', 'anonymous', NULL);
        $token = new Google_Analytics_OAuthConsumer($this->oauth_token, $this->oauth_secret);
        $oauth_req = Google_Analytics_OAuthRequest::from_consumer_and_token($consumer, $token, 'GET', $url, array());
        $oauth_req->sign_request($signature_method, $consumer, $token);

        $headers = $oauth_req->to_header();
        $headers = explode(": ",$headers);
        $headers[$headers[0]] = $headers[1];

        return $headers;
    }

    function get_accounts() {
        global $google_analytics_async;

        //when getting account we need current settings... always.
        $this->oauth_token = $google_analytics_async->current_settings['google_login']['token'];
        $this->oauth_secret = $google_analytics_async->current_settings['google_login']['token_secret'];

        $headers = $this->prepare_authentication_header($this->account_base_url_new.'accounts/~all/webproperties/~all/profiles');
        $response = wp_remote_get($this->account_base_url_new.'accounts/~all/webproperties/~all/profiles', array('sslverify' => false, 'headers' => $headers));
        if(is_wp_error($response)) {
            $this->error = $response->get_error_message();
            return false;
        }
        else {
            $this->http_code = wp_remote_retrieve_response_code( $response );
            $response_body = wp_remote_retrieve_body($response);

            if($this->http_code != 200) {
                $this->error = $response_body;
                return false;
            }
            else {
                $response = json_decode($response_body);
                $this->error = '';
                $host_ready = '';

                $current_site_url = rtrim(get_site_url(), "/");

                global $google_analytics_async;
                $is_network = is_network_admin() ? 'network' : '';

                $accounts = array();
                foreach($response->items as $analytics_profile) {
                    $tracking_code = $analytics_profile->webPropertyId;
                    $account_id = 'ga:'.$analytics_profile->id;
                    $title = $analytics_profile->name;
                    $website_url = rtrim($analytics_profile->websiteUrl, "/");

                    $this->profile_id = $account_id;
                    if(!isset($save_settings) && (empty($google_analytics_async->current_settings['track_settings']['tracking_code']) || empty($google_analytics_async->current_settings['track_settings']['google_analytics_account_id']))) {
                        if($current_site_url == $website_url) {
                            if(empty($google_analytics_async->current_settings['track_settings']['tracking_code'])){
                                $google_analytics_async->current_settings['track_settings']['tracking_code'] = $tracking_code;
                            }
                            if(empty($google_analytics_async->current_settings['track_settings']['google_analytics_account_id'])) {
                                $google_analytics_async->current_settings['track_settings']['google_analytics_account_id'] = $account_id;
                            }
                            $google_analytics_async->save_options( $google_analytics_async->current_settings, $is_network );
                        }
                    }

                    $accounts[$account_id] = $title.' - '.$website_url.' ('.$tracking_code.')';
                }

                return $accounts;
            }
        }
    }

    //load correct data and prepare to display
    function load_google_analytics() {
        if(!current_user_can( $this->required_capability ))
            die(__('Cheatin&#8217; uh?'));

        //if no cache, data will be requested by ajax
        if(!$this->cache) {
            //set up correct chart details for choosen date range
            if($this->date_range == 3)
                $date_details = array('dimension' => 'ga:yearWeek', 'format' => 'M W');
            elseif($this->date_range == 12)
                $date_details = array('dimension' => 'ga:yearMonth', 'format' => 'M Y');
            else
                $date_details = array('dimension' => 'ga:date', 'format' => 'M d');

            $dates_data = $this->request('simple', '', $date_details['dimension'], 'ga:visits,ga:pageviews,ga:newVisits');
            //Load advanced statistics
            if($this->type == 'statistics_page' || $this->type == 'post') {
                if(!$this->error)
                    $summary_data = $this->request('simple', '', '','ga:visits,ga:pageviews,ga:newVisits,ga:percentNewVisits,ga:visitBounceRate,ga:avgTimeOnSite,ga:pageviewsPerVisit,ga:percentNewVisits');
                if(!$this->error)
                    $keywords_data = $this->request('simple', 9, 'ga:keyword', 'ga:visits', '-ga:visits');
                if(!$this->error)
                    $sources_data = $this->request('simple', 9, 'ga:source', 'ga:visits', '-ga:visits');
                //Load statistics for statistics page
                if($this->type == 'statistics_page') {
                    if(!$this->error && !$this->post)
                        $pages_data = $this->request('advanced', 20, 'ga:hostname,ga:pageTitle,ga:pagePath', 'ga:pageviews,ga:visits,ga:newVisits', '-ga:visits');
                    if(!$this->error)
                        $countries_data = $this->request('simple', 20, 'ga:country', 'ga:visits', '-ga:visits');
                }
            }

            $stats = array();
            if($this->error) {
                if (strpos($this->error,'GDataauthErrorAuthorizationInvalid') !== false) {
                    if(is_network_admin())
                        $settings_page_url = admin_url('network/settings.php?page=google-analytics');
                    else
                        $settings_page_url = admin_url('options-general.php?page=google-analytics');
                    $this->error = '<a href="'.$settings_page_url.'">'.__('Please logout and login to Google Account.', $this->text_domain).'</a>';
                }
                $return['html'] = __('Error loading data. ', $this->text_domain).' '.$this->error;
            }
            else {
                $translate_dates = array(
                    'Jan' => __('Jan', $this->text_domain),
                    'Feb' => __('Feb', $this->text_domain),
                    'Mar' => __('Mar', $this->text_domain),
                    'Apr' => __('Apr', $this->text_domain),
                    'May' => __('May', $this->text_domain),
                    'Jun' => __('Jun', $this->text_domain),
                    'Jul' => __('Jul', $this->text_domain),
                    'Aug' => __('Aug', $this->text_domain),
                    'Sep' => __('Sep', $this->text_domain),
                    'Oct' => __('Oct', $this->text_domain),
                    'Nov' => __('Nov', $this->text_domain),
                    'Dec' => __('Dec', $this->text_domain)
                );
                $stats['chart_visitors'] = array(array(__('Date', $this->text_domain), __('Pageviews', $this->text_domain), __('Visits', $this->text_domain), __('Unique Visitors', $this->text_domain)));
                foreach($dates_data as $day => $data) {
                    if($this->date_range == 3)
                        $day = substr_replace($day, 'W', 4, 0);
                    elseif($this->date_range == 12)
                        $day = $day.'01';

                    $time = strtotime($day)+(get_option('gmt_offset')*60*60);

                    $date = str_replace(date('M', $time), $translate_dates[date('M', $time)], date($date_details['format'], $time));
                    $stats['chart_visitors'][] = array($date, (int)$data['ga:pageviews'], (int)$data['ga:visits'], (int)$data['ga:newVisits']);
                }
                $return['chart_visitors'] = $stats['chart_visitors'];

                //setup advanced statistics
                if($this->type == 'statistics_page' || $this->type == 'post') {
                    $stats['top_posts'] = $top_searches = $top_referers = array();

                    $stats['visits'] = isset($summary_data['value']['ga:visits']) ? number_format($summary_data['value']['ga:visits']) : '-';
                    $stats['pageviews'] = isset($summary_data['value']['ga:pageviews']) ? number_format($summary_data['value']['ga:pageviews']) : '-';
                    $stats['unique_visitors'] = isset($summary_data['value']['ga:newVisits']) ? number_format($summary_data['value']['ga:newVisits']) : '-';
                    $stats['page_per_visit'] = isset($summary_data['value']['ga:pageviewsPerVisit']) ? number_format($summary_data['value']['ga:pageviewsPerVisit']) : '-';
                    $stats['bounce_rate'] = isset($summary_data['value']['ga:visitBounceRate']) ? number_format($summary_data['value']['ga:visitBounceRate']).'%' : '-';
                    $stats['avg_visit_duration'] = isset($summary_data['value']['ga:avgTimeOnSite']) ? date("H:i:s",$summary_data['value']['ga:avgTimeOnSite']) : '-';
                    $stats['new_visits'] = isset($summary_data['value']['ga:percentNewVisits']) ? number_format($summary_data['value']['ga:percentNewVisits']).'%' : '-';

                    foreach($keywords_data as $keyword => $stat)
                        if($keyword != "(not set)")
                            $stats['top_searches'][] = array('keyword' => $keyword, 'stat' => $stat);

                    foreach($sources_data as $source => $stat)
                        if($source != "(not set)")
                            $stats['top_referers'][] = array('source' => $source, 'stat' => $stat);

                    //setup statistics for statistics page
                    if($this->type == 'statistics_page') {
                        if(isset($pages_data))
                            foreach($pages_data as $page) {
                                $url = $page['value'];
                                $title = $page['children']['value'];
                                $pageviews = $page['children']['children']['children']['ga:pageviews'];
                                $visits = $page['children']['children']['children']['ga:visits'];
                                $unique_visitors = $page['children']['children']['children']['ga:newVisits'];
                                $host = $page['children']['children']['value'];

                                $stats['top_pages'][] = array('host' => $host, 'url' => $url, 'title' => $title, 'pageviews' => $pageviews, 'visits' => $visits, 'unique_visitors' => $unique_visitors);
                            }

                        $stats['chart_countries'] = array(array(__('Country', $this->text_domain), __('Visits', $this->text_domain)));
                        foreach($countries_data as $country => $visits)
                            $stats['chart_countries'][] = array($country, (int)$visits);
                        $return['chart_countries'] = $stats['chart_countries'];
                    }
                }

                //set cache data if its all good
                set_transient($this->cache_name, $stats, $this->cache_timeout);

                //prepare correct data for ajax return
                if($this->type == 'post')
                    $return['html'] = $this->google_analytics_widget_extended_html($stats);
                elseif($this->type == 'statistics_page')
                    $return['html'] = $this->google_analytics_statistics_page_html($stats);
                else
                    $return['html'] = $this->google_analytics_widget_html($stats);
            }

            echo json_encode($return);
            die();
        }
        else
            //prepare correct data cache based return
            if($this->type == 'post')
                return $this->google_analytics_widget_extended_html($this->cache);
            elseif($this->type == 'statistics_page')
                return $this->google_analytics_statistics_page_html($this->cache);
            else
                return $this->google_analytics_widget_html($this->cache);
    }

    function google_analytics_widget() {
        if($this->is_network_admin)
            $statistics_page_url = admin_url('network/index.php?page=google-analytics-statistics');
        else
            $statistics_page_url = admin_url('index.php?page=google-analytics-statistics');

        if(!$this->cache) {
            if($this->type == 'post' && $this->load_mode == 'soft')
                $text = '<p class="post-loader"><a id="load-post-stats" class="button button-primary" href="#">'.__('Load Post Stats', $this->text_domain).'</a><span class="loading"><img alt="'.__( 'Loading...', $this->text_domain ).'" src="'.includes_url('images/spinner-2x.gif').'"/></span></p>';
            else
                $text = '<p>'.__('Loading...', $this->text_domain).'</p>';
            echo '<div id="google-analytics-widget">'.$text.'</div><p class="textright"><a class="button button-primary" href="'.$statistics_page_url.'">'.__('See All Stats', $this->text_domain).'</a></p>';
        }
        else
            echo '<div id="google-analytics-widget">'.$this->load_google_analytics().'</div><p class="textright"><a class="button button-primary" href="'.$statistics_page_url.'">'.__('See All Stats', $this->text_domain).'</a></p>';
    }

    function google_analytics_widget_html($stats) {
        $return = '
            <div class="google_analytics_chart_holder">
                <div id="google-analytics-chart-visitors" class="google_analytics_chart" style="width: 100%; height: 300px;"></div>
            </div>';

        return $return;
    }

    function google_analytics_widget_extended_html($stats) {
        $return = '
            <div class="google_analytics_chart_holder">
                <div id="google-analytics-chart-visitors" class="google_analytics_chart" style="width: 100%; height: 300px;"></div>
            </div>

            <div class="google-analytics-basic-stats">
                <ul>
                    <li><label>'.__( 'Visits', $this->text_domain ).'</label><span>'.$stats['visits'].'</span></li>
                    <li><label>'.__( 'Unique Visitors', $this->text_domain ).'</label><span>'.$stats['unique_visitors'].'</span></li>
                    <li><label>'.__( 'Pageviews', $this->text_domain ).'</label><span>'.$stats['pageviews'].'</span></li>
                    <li><label>'.__( 'Pages / Visit', $this->text_domain ).'</label><span>'.$stats['page_per_visit'].'</span></li>
                    <li><label>'.__( 'Bounce Rate', $this->text_domain ).'</label><span>'.$stats['bounce_rate'].'</span></li>
                    <li><label>'.__( 'Avg. Visit Dur.', $this->text_domain ).'</label><span>'.$stats['avg_visit_duration'].'</span></li>
                    <li><label>'.__( 'New Visits', $this->text_domain ).'</label><span>'.$stats['new_visits'].'</span></li>
                </ul>
            </div>';
        if((isset($stats['top_searches']) && $stats['top_searches']) || (isset($stats['top_referers']) && $stats['top_referers'])) {
            $return .= '
                <div class="google-analytics-extended-stats">';

                if((isset($stats['top_searches']) && $stats['top_searches']) || (isset($stats['top_referers']) && $stats['top_referers'])) {
                    $return .= '
                        <div class="google_analytics_top_searches_referrals">';

                    if(isset($stats['top_searches']) && $stats['top_searches']) {
                        $top_searches = array();
                        foreach ($stats['top_searches'] as $key => $data)
                            $top_searches[] = '<tr><td>'.$data['keyword'].'</td><td align="right">'.$data['stat'].'</td></tr>';

                        $return .= '
                            <div id="postcustomstuff" class="google-analytics-searches">
                                <table class="wp-list-table widefat google-analytics-table">
                                    <thead>
                                        <tr>
                                            <th>'.__( 'Top Searches', $this->text_domain ).'</th>
                                            <th class="right">'.__( 'Visits', $this->text_domain ).'</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    '.implode($top_searches).'
                                    </tbody>
                                </table>
                            </div>';
                    }

                    if(isset($stats['top_referers']) && $stats['top_referers']) {
                        $top_referers = array();
                        foreach ($stats['top_referers'] as $key => $data)
                            $top_referers[] = '<tr><td>'.$data['source'].'</td><td align="right">'.$data['stat'].'</td></tr>';

                        $return .= '
                            <div id="postcustomstuff" class="google-analytics-top-referrals last">
                                <table class="wp-list-table widefat google-analytics-table">
                                    <thead>
                                        <tr>
                                            <th>'.__( 'Top Referrals', $this->text_domain ).'</th>
                                            <th class="right">'.__( 'Visits', $this->text_domain ).'</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        '.implode($top_referers).'
                                    </tbody>
                                </table>
                            </div>';
                    }

                    $return .= '
                        </div>';
                }
            $return .= '
                </div>
            ';
        }

        return $return;
    }

    function google_analytics_statistics_page() {
        if(!$this->cache)
            echo '<div id="google-analytics-statistics-page"><div class="loading"><img alt="'.__( 'Loading...', $this->text_domain ).'" src="'.includes_url('images/spinner-2x.gif').'"/></div></div>';
        else
            echo '<div id="google-analytics-statistics-page">'.$this->load_google_analytics().'</div>';
    }

    function google_analytics_statistics_page_html($stats) {
        if($this->date_range == 1)
            $statistics_description = ' - '.__( 'Last Month', $this->text_domain );
        elseif($this->date_range == 3)
            $statistics_description = ' - '.__( 'Last 3 Months', $this->text_domain );
        elseif($this->date_range == 12)
            $statistics_description = ' - '.__( 'Last Year', $this->text_domain );

        if($this->is_network_admin)
            $statistics_page_url = admin_url('network/index.php?page=google-analytics-statistics');
        else
            $statistics_page_url = admin_url('index.php?page=google-analytics-statistics');

        $return = '
            <div class="wrap">
                <h2>
                    '.__( 'Statistics', $this->text_domain ).$statistics_description.'
                    <a href="'.add_query_arg('date_range', false, $statistics_page_url).'" class="add-new-h2">'.__( 'Last Month', $this->text_domain ).'</a>
                    <a href="'.add_query_arg('date_range', 3, $statistics_page_url).'" class="add-new-h2">'.__( 'Last 3 Months', $this->text_domain ).'</a>
                    <a href="'.add_query_arg('date_range', 12, $statistics_page_url).'" class="add-new-h2">'.__( 'Last Year', $this->text_domain ).'</a>
                </h2>

                <div id="poststuff">
                    <div id="post-body" class="metabox-holder columns-2">
                        <div id="postbox-container-1" class="postbox-container">

                            <div id="side-sortables" class="meta-box-sortables ui-sortable">
                                <div class="postbox google-analytics-countries">
                                    <h3 class="hndle"><span>'.__( 'Visitors: Country', $this->text_domain ).'</span></h3>
                                    <div class="inside">
                                        <div id="google-analytics-chart-countries" class="google-analytics-chart"></div>
                                    </div>
                                </div>
                            </div>

                            <div id="side-sortables" class="meta-box-sortables ui-sortable">
                                <div class="postbox google_analytics_top_searches_referrals">
                                    <h3 class="hndle"><span>'.__( 'Referrers', $this->text_domain ).'</span></h3>
                                    <div class="inside">';



                                        $return .= '
                                            <div id="postcustomstuff" class="google-analytics-searches">
                                                <table class="wp-list-table widefat google-analytics-table">
                                                    <thead>
                                                        <tr>
                                                            <th>'.__( 'Top Searches', $this->text_domain ).'</th>
                                                            <th>'.__( 'Visits', $this->text_domain ).'</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>';

                                            if(isset($stats['top_searches']) && $stats['top_searches']) {
                                                $top_searches = array();
                                                foreach ($stats['top_searches'] as $key => $data)
                                                    $return .= '
                                                        <tr><td>'.$data['keyword'].'</td><td>'.$data['stat'].'</td></tr>';
                                            }
                                            else
                                                $return .= '
                                                        <tr><td colspan="2">'.__( 'Data is not available yet', $this->text_domain ).'</td></tr>';

                                                $return .= '
                                                    </tbody>
                                                </table>
                                            </div>

                                            <div id="postcustomstuff" class="google-analytics-top-referrals last">
                                                <table class="wp-list-table widefat google-analytics-table">
                                                    <thead>
                                                        <tr>
                                                            <th>'.__( 'Top Referrals', $this->text_domain ).'</th>
                                                            <th>'.__( 'Visits', $this->text_domain ).'</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>';
                                            if(isset($stats['top_referers']) && $stats['top_referers']) {
                                                $top_referers = array();
                                                foreach ($stats['top_referers'] as $key => $data)
                                                    $return .= '
                                                        <tr><td>'.$data['source'].'</td><td>'.$data['stat'].'</td></tr>';
                                            }
                                            else
                                                $return .= '
                                                        <tr><td colspan="2">'.__( 'Data is not available yet', $this->text_domain ).'</td></tr>';

                                                $return .= '
                                                    </tbody>
                                                </table>
                                            </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="postbox-container-2" class="postbox-container">
                            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                                <div class="postbox">
                                    <h3 class="hndle"><span>'.__( 'Visitors', $this->text_domain ).'</span></h3>
                                    <div class="inside">
                                        <div id="google-analytics-chart-visitors" class="google_analytics_chart"></div>
                                        <div class="google-analytics-basic-stats">
                                            <ul>
                                                <li><label>'.__( 'Visits', $this->text_domain ).'</label><span>'.$stats['visits'].'</span></li>
                                                <li><label>'.__( 'Unique Visitors', $this->text_domain ).'</label><span>'.$stats['unique_visitors'].'</span></li>
                                                <li><label>'.__( 'Pageviews', $this->text_domain ).'</label><span>'.$stats['pageviews'].'</span></li>
                                                <li><label>'.__( 'Pages / Visit', $this->text_domain ).'</label><span>'.$stats['page_per_visit'].'</span></li>
                                                <li><label>'.__( 'Bounce Rate', $this->text_domain ).'</label><span>'.$stats['bounce_rate'].'</span></li>
                                                <li><label>'.__( 'Avg. Visit Dur.', $this->text_domain ).'</label><span>'.$stats['avg_visit_duration'].'</span></li>
                                                <li><label>'.__( 'New Visits', $this->text_domain ).'</label><span>'.$stats['new_visits'].'</span></li>
                                            </ul>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div id="normal-sortables" class="meta-box-sortables ui-sortable">
                                <div class="postbox">
                                    <h3 class="hndle"><span>'.__( 'Content', $this->text_domain ).'</span></h3>
                                    <div class="inside">
                                        <div id="postcustomstuff" class="google-analytics-top_posts-pages">
                                            <table class="wp-list-table widefat google-analytics-table">
                                                <thead>
                                                    <tr>
                                                        <th>'.__( 'Top Posts / Pages', $this->text_domain ).'</th>
                                                        <th>'.__( 'Visits', $this->text_domain ).'</th>
                                                        <th>'.__( 'Unique', $this->text_domain ).'</th>
                                                        <th>'.__( 'Views', $this->text_domain ).'</th>
                                                    </tr>
                                                </thead>
                                                <tbody>';
                                        if(isset($stats['top_pages']) && $stats['top_pages']) {
                                            //fixes for top pages to generate correct URL and merge data
                                            $pages_ready = array();
                                            foreach ($stats['top_pages'] as $key => $data) {
                                                if (strpos(substr($data['url'], 1), $data['host']) === 0)
                                                    $url = substr($data['url'], 1);
                                                elseif(strpos($data['url'], $data['host']) === 0)
                                                    $url = $data['url'];
                                                else
                                                    $url = $data['host'].$data['url'];

                                                if(!array_key_exists($url, $pages_ready))
                                                    $pages_ready[$url] = $data;
                                                else {
                                                    $pages_ready[$url]['visits'] = $pages_ready[$url]['visits'] + $data['visits'];
                                                    $pages_ready[$url]['unique_visitors'] = $pages_ready[$url]['unique_visitors'] + $data['unique_visitors'];
                                                    $pages_ready[$url]['pageviews'] = $pages_ready[$url]['pageviews'] + $data['pageviews'];
                                                }
                                             }
                                            foreach ($pages_ready as $url => $data) {
                                                $return .= '
                                                    <tr><td><a href="http://'.$url.'">'.$data['title'].'</a></td><td>'.$data['visits'].'</td><td>'.$data['unique_visitors'].'</td><td>'.$data['pageviews'].'</td></tr>';
                                            }
                                        }
                                        else
                                            $return .= '
                                                    <tr><td colspan="3">'.__( 'Data is not available yet', $this->text_domain ).'</td></tr>';

                                            $return .= '
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        ';

        return $return;
    }

    function request($type, $max_results = '', $dimensions = '', $metrics = '', $sort = '') {
        $url_parameters = array(
            'ids' => $this->profile_id,
            'start-date' => $this->start_date,
            'end-date' => $this->end_date
        );
        if(!empty($max_results))
            $url_parameters['max-results'] = $max_results;
        if(!empty($dimensions))
            $url_parameters['dimensions'] = $dimensions;
        if(!empty($metrics))
            $url_parameters['metrics'] = $metrics;
        if(!empty($sort))
            $url_parameters['sort'] = $sort;
        if(count($this->filter) > 0)
            $url_parameters['filters'] = implode(';', $this->filter);
        $url = add_query_arg($url_parameters, $this->base_url . 'data');

        $response = wp_remote_get($url, array('sslverify' => false, 'headers' => $this->prepare_authentication_header($url)));
        if($response && is_wp_error($response)) {
            $this->error = $response->get_error_message();
            return false;
        }
        else {
            $this->http_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);

            if($this->http_code != 200) {
                $this->error = $response_body;
                return false;
            }
            else {
                $xml = simplexml_load_string($response_body);

                $return_values = array();
                foreach($xml->entry as $entry) {
                    if($type == 'simple') {
                        if($dimensions == '')
                            $dim_name = 'value';
                        else {
                            $dimension = $entry->xpath('dxp:dimension');
                            $dimension_attributes = $dimension[0]->attributes();
                            $dim_name = (string)$dimension_attributes['value'];
                        }

                        $metric = $entry->xpath('dxp:metric');
                        if(sizeof($metric) > 1) {
                            foreach($metric as $single_metric) {
                                $metric_attributes = $single_metric->attributes();
                                $return_values[$dim_name][(string)$metric_attributes['name']] = (string)$metric_attributes['value'];
                            }
                        }
                        else {
                            $metric_attributes = $metric[0]->attributes();
                            $return_values[$dim_name] = (string)$metric_attributes['value'];
                        }
                    }
                    else {
                        $metrics = array();
                        foreach($entry->xpath('dxp:metric') as $metric) {
                            $metric_attributes = $metric->attributes();
                            $metrics[(string)$metric_attributes['name']] = (string)$metric_attributes['value'];
                        }

                        $last_dimension_var_name = null;
                        foreach($entry->xpath('dxp:dimension') as $dimension) {
                            $dimension_attributes = $dimension->attributes();

                            $dimension_var_name = 'dimensions_' . strtr((string)$dimension_attributes['name'], ':', '_');
                            $$dimension_var_name = array();

                            if($last_dimension_var_name == null)
                                $$dimension_var_name = array('name' => (string)$dimension_attributes['name'],'value' => (string)$dimension_attributes['value'],'children' => $metrics);
                            else
                                $$dimension_var_name = array('name' => (string)$dimension_attributes['name'],'value' => (string)$dimension_attributes['value'],'children' => $$last_dimension_var_name);

                            $last_dimension_var_name = $dimension_var_name;
                        }
                        array_push($return_values, $$last_dimension_var_name);
                    }
                }

                return $return_values;
            }
        }
    }
}

global $google_analytics_async_dashboard;
$google_analytics_async_dashboard = new Google_Analytics_Async_Dashboard();
?>