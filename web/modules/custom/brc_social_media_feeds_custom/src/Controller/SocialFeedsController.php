<?php
/**
 * @file
 * Contains \Drupal\brc_social_media_feeds_custom\Controller\SocialFeedsController.
 */

namespace Drupal\brc_social_media_feeds_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Config\ConfigFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use GuzzleHttp\Client;
use Google\Client as Google_Client;
use TwitterAPIExchange;

class SocialFeedsController extends ControllerBase {

  public $type;

  public $page;

  public $config;

  public function __construct(ConfigFactory $config_factory) {
    $this->config = $config_factory->get('brc_social_media_feeds_custom.settings');

    if (isset($_REQUEST['type'])) {
      $this->type = $_REQUEST['type'];
    }else {
      $this->type = 'todas';
    }
    if (isset($_REQUEST['page'])) {
      $this->page = $_REQUEST['page'];
    }else {
      $this->page = '';
    }
  }

  public static function create(ContainerInterface $container) {
    return new static($container->get('config.factory'));
  }

  public function feeds() {
    $results = file_get_contents('sites/default/files/media/social_feeds.json');
    $backup_feeds = json_decode($results, true);
    $pages = null;

    switch ($this->type) {
      case 'facebook':
        $results = $this->feedsFb($pages);
        break;
      case 'twitter':
        $results = $this->feedsTw($pages);
        break;
      case 'instagram':
        $results = $this->feedsIg($pages);
        break;
      case 'youtube':
        $results = $this->feedsYt($pages);
        break;
      default:
        $pageTokens = !empty($this->page) ? explode('*', $this->page) : [];
        $pageFBToken = !empty($this->page) ? $pageTokens[0] : null;
        $pageTWToken = !empty($this->page) ? $pageTokens[1] : null;
        $pageINToken = !empty($this->page) ? $pageTokens[2] : null;
        $pageYTToken = !empty($this->page) ? $pageTokens[3] : null;

        $fb = $this->feedsFb($pageFBToken);
        $tw = $this->feedsTw($pageTWToken);
        $in = $this->feedsIg($pageINToken);
        $yt = $this->feedsYt($pageYTToken);
        $fb['data'] = (isset($fb['data']) && !empty($fb['data'])) ? $fb['data'] : $backup_feeds['data']['Facebook'];
        $tw['data'] = (isset($tw['data']) && !empty($tw['data'])) ? $tw['data'] : $backup_feeds['data']['Twitter'];
        $in['data'] = (isset($in['data']) && !empty($in['data'])) ? $in['data'] : $backup_feeds['data']['Instagram'];
        $yt['data'] = (isset($yt['data']) && !empty($yt['data'])) ? $yt['data'] : $backup_feeds['data']['YouTube'];
        $fb['pagination'] = (isset($fb['pagination']) && !empty($fb['pagination'])) ? $fb['pagination'] : $backup_feeds['pagination']['Facebook'];
        $tw['pagination'] = (isset($tw['pagination']) && !empty($tw['pagination'])) ? $tw['pagination'] : $backup_feeds['pagination']['Twitter'];
        $in['pagination'] = (isset($in['pagination']) && !empty($in['pagination'])) ? $in['pagination'] : $backup_feeds['pagination']['Instagram'];
        $yt['pagination'] = (isset($yt['pagination']) && !empty($yt['pagination'])) ? $yt['pagination'] : $backup_feeds['pagination']['YouTube'];

        $data = array_merge(
          $fb['data'], 
          $tw['data'],
          $in['data'],
          $yt['data']
        );
        usort($data, function($a, $b) {return $b['order'] - $a['order'];});

        $results = array(
          'data' => array(
            'Facebook' => $fb['data'],
            'Twitter' => $tw['data'],
            'Instagram' => $in['data'],
            'YouTube' => $yt['data'],
            'Todas' => $data
          ),
          'pagination' => array(
            'Facebook' => $fb['pagination'],
            'Twitter' => $tw['pagination'],
            'Instagram' => $in['pagination'],
            'YouTube' => $yt['pagination'],
            'Todas' => array(
              'nextPage' => $fb['pagination']['nextPage'].'*'.$tw['pagination']['nextPage'].'*'.$in['pagination']['nextPage'].'*'.$yt['pagination']['nextPage'],//$page,
              'resultsPerPage' => sizeof($data),
              'totalResults' => sizeof($data)
            )
          )
        );

        $fp = fopen('sites/default/files/media/social_feeds.json', 'w');
        fwrite($fp, json_encode($results,JSON_UNESCAPED_UNICODE));
        fwrite($fp, "\n");
        fclose($fp);
  
    }
    return new JsonResponse($results);
  }

  public function feedsFb($nextPage) {
    $feeds = ['data' => [],'pagination'=> ['nextPage' => '','prevPage' => '','resultsPerPage' => 0,'totalResults' => 0]];
    $accounts  = explode(',', $this->config->get('accounts_fb'));
    $access_token = $this->config->get('access_token_fb');
   
    if (empty($nextPage)) {
      foreach ($accounts as $key => $account) {
        $url = 'https://graph.facebook.com/v3.2/'.$account.'?';
        $parameters = array (
          'fields' => 'posts{message,actions,created_time,call_to_action,feed_targeting,full_picture}',
          'access_token' => $access_token
        );
        $endpoint = $url . http_build_query($parameters);
        $feeds = $this->depure($this->curl_fb($endpoint), 'Facebook', $account, $feeds);
      }
      $feeds['data'] = $this->arraySortBy($feeds['data'], 'date', SORT_DESC);
    }
    if (!empty($nextPage)) {
      $pages = explode('$', $nextPage);
      foreach ($accounts as $key => $account) {
        $url = 'https://graph.facebook.com/v3.2/'.$account.'?';
        $parameters = array (
          'fields' => 'posts{message,actions,created_time,call_to_action,feed_targeting,full_picture}',
          'access_token' => $access_token,
          'limit' => '25',
          'after' => $pages[$key]
        );
        $endpoint = $url . http_build_query($parameters);
        $feeds = $this->depure($this->curl_fb($endpoint), 'Facebook', $account, $feeds);
      }
      $feeds['data'] = $this->arraySortBy($feeds['data'], 'date', SORT_DESC);
    }
    return $feeds;
  }

  public function feedsTw($nextPage) {
    $settings = array(
      'oauth_access_token' => $this->config->get('oauth_access_token_tw'),
      'oauth_access_token_secret' => $this->config->get('oauth_access_token_secret_tw'),
      'consumer_key' => $this->config->get('consumer_key_tw'),
      'consumer_secret' => $this->config->get('consumer_secret_tw'),
    );
    
    $url = 'https://api.twitter.com/1.1/statuses/user_timeline.json';
    if (empty($nextPage)) {
      $parameters = array (
        'screen_name' => $this->config->get('accounts_tw'),
        'tweet_mode' => 'extended',
        'count' => '76',
        'lang' => 'es',
      ); 
    }else {
      $parameters = array (
        'screen_name' => $this->config->get('accounts_tw'),
        'tweet_mode' => 'extended',
        'count' => '76',
        'lang' => 'es',
        'max_id' => $nextPage,
      ); 
    }

    $getfield = http_build_query($parameters);
    $requestMethod = 'GET';

    $twitter = new TwitterAPIExchange($settings);
    $response = $twitter->setGetfield($getfield)
      ->buildOauth($url, $requestMethod)
      ->performRequest();
    $results = $this->depure(json_decode($response), 'Twitter');
    return $results;
  }

  public function feedsIg($nextPage) {
    $feeds = ['data' => [],'pagination'=> ['nextPage' => '','prevPage' => '','resultsPerPage' => 0,'totalResults' => 0]];
    $accounts  = explode(',', $this->config->get('accounts_ig'));
    $access_token = $this->config->get('access_token_ig');
   
    if (empty($nextPage)) {
      foreach ($accounts as $key => $account) {
        $url = 'https://graph.facebook.com/v9.0/'.$account.'/media?';
        $parameters = array (
          'fields' => 'caption,id,media_url,permalink,thumbnail_url,timestamp,username',
          'access_token' => $access_token
        );
        $endpoint = $url . http_build_query($parameters);
        $feeds = $this->depure($this->curl_fb($endpoint), 'Instagram', $account, $feeds);
      }
      $feeds['data'] = $this->arraySortBy($feeds['data'], 'date', SORT_DESC);
    }
    if (!empty($nextPage)) {
      $pages = explode('$', $nextPage);
      foreach ($accounts as $key => $account) {
        $url = 'https://graph.facebook.com/v3.2/'.$account.'/posts?';
        $parameters = array (
          'fields' => 'caption,id,media_url,permalink,thumbnail_url,timestamp,username',
          'access_token' => $access_token,
          'limit' => '25',
          'after' => $pages[$key]
        );
        $endpoint = $url . http_build_query($parameters);
        $feeds = $this->depure($this->curl_fb($endpoint), 'Instagram', $account, $feeds);
      }
      $feeds['data'] = $this->arraySortBy($feeds['data'], 'date', SORT_DESC);
    }
    return $feeds;
  }

  public function feedsYt($nextPage) {
    try {
      $nextPage = isset($nextPage) ? $nextPage : '';
      $developer_key = $this->config->get('developer_key_yt');
      
      $client = new Google_Client();
      $client->setDeveloperKey($developer_key);
      $youtube = new \Google_Service_YouTube($client);
      $parameters = array(
          'channelId' => $this->config->get('channel_id_yt'),
          'maxResults' => '50',
          'order' => 'date',
          'pageToken' => $nextPage
      );
      $searchResponse = $youtube->search->listSearch('id,snippet', $parameters);
      $results = $this->depure($searchResponse, 'YouTube');
    } catch (Exception $e) {
      $m = $e->getMessage();
    }
    return $results;
  }

  public function curl_fb($endpoint) {
    try {
      $client = new Client();
      $res = $client->get($endpoint);
      if($res->getStatusCode() == 200) {    
        return json_decode($res->getBody()->getContents());
      }
    }
    catch (RequestException $e) {
      watchdog_exception('brc_social_media_feeds_custom', $e->getMessage());
    }
  }

  public function depure($data, $social_network, $account = null, $old_feeds = null) {
    $feeds = array(
      'data' => array(),
      'pagination'=> array()
    );

    switch ($social_network) {
      case 'Facebook':
        $data_feeds = isset($data->posts->data) ? $data->posts->data : $data->data;
        foreach ($data_feeds as $key => $item) {
          $date = new \DateTimeImmutable($item->created_time, new \DateTimeZone('UTC'));

          array_push($feeds['data'], array(
            'id' => $item->id,
            'title' => isset($item->message) ? $this->getInfoAccount($item->message, 'Facebook') : '',
            'social_network' => 'Facebook',
            'account' => $account,
            'image' => isset($item->full_picture) ? $item->full_picture : '',
            'date' => $date->modify('-5 hours')->format('Y/m/d H:i:s'),
            'order' => $date->modify('-5 hours')->format('YmdHis'),
            'link' => $item->actions[0]->link,
            'icon' => 'icon-facebook',
            'text_length' => isset($item->message) ? strlen(strip_tags($item->message)) : 0
          ));
        }
        $feeds['data'] = array_merge($old_feeds['data'], $feeds['data']);
        $data_pag = isset($data->posts->paging) ? $data->posts->paging : $data->paging;
        $feeds['pagination'] = array(
          'nextPage' => empty($old_feeds['pagination']['nextPage']) ? $data_pag->cursors->after : $old_feeds['pagination']['nextPage'].'$' .$data_pag->cursors->after,
          'prevPage' => empty($old_feeds['pagination']['prevPage']) ? $data_pag->cursors->before : $old_feeds['pagination']['prevPage'].'$' .$data_pag->cursors->before,
          'resultsPerPage' => sizeof($feeds['data']),
          'totalResults' => sizeof($feeds['data'])
        );
        break;
      case 'Twitter':
        foreach ($data as $key => $item) {
          $date = new \DateTimeImmutable($item->created_at, new \DateTimeZone('UTC'));

          array_push($feeds['data'], array(
            'id' => $item->id,
            'title' => $this->getInfoAccount($item->full_text, 'Twitter'),
            'description' => '',
            'social_network' => 'Twitter',
            'account' => 'Banrepcultural',
            'image' => isset($item->entities->media) && !empty($item->entities->media) ? $item->entities->media[0]->media_url_https : '',
            'date' => $date->modify('-5 hours')->format('Y/m/d H:i:s'),
            'order' => $date->modify('-5 hours')->format('YmdHis'),
            'link' => isset($item->entities->media) && !empty($item->entities->media) ? $item->entities->media[0]->url : '',
            'path' => isset($item->entities->urls) && !empty($item->entities->urls) ? $item->entities->urls[0]->url : '',
            'icon' => 'icon-twitter',
            'text_length' => isset($item->full_text) ? strlen(strip_tags($item->full_text)) : 0
          ));
        }
        unset($feeds['data'][sizeof($feeds['data'])-1]);
        $feeds['pagination'] = array(
          'nextPage' => $data[sizeof($data)-1]->id,
          'prevPage' => $data[0]->id,
          'resultsPerPage' => sizeof($feeds['data']),
          'totalResults' => sizeof($feeds['data'])
        );
        break;
      case 'Instagram':
        $data_feeds = isset($data->data) ? $data->data : [];
        foreach ($data_feeds as $key => $item) {
          $date = new \DateTimeImmutable($item->created_time, new \DateTimeZone('UTC'));

          array_push($feeds['data'], array(
            'id' => $item->id,
            'title' => isset($item->caption) ? $this->getInfoAccount($item->caption, 'Instagram') : '',
            'social_network' => 'Instagram',
            'account' => $item->username,
            'image' => isset($item->media_url) ? $item->media_url : '',
            'date' => $date->modify('-5 hours')->format('Y/m/d H:i:s'),
            'order' => $date->modify('-5 hours')->format('YmdHis'),
            'link' => $item->permalink,
            'icon' => 'icon-instagram',
            'text_length' => isset($item->caption) ? strlen(strip_tags($item->caption)) : 0
          ));
        }
        $feeds['data'] = array_merge($old_feeds['data'], $feeds['data']);
        $data_pag = isset($data->paging) ? $data->paging : $data->paging;
        $feeds['pagination'] = array(
          'nextPage' => empty($old_feeds['pagination']['nextPage']) ? $data_pag->cursors->after : $old_feeds['pagination']['nextPage'].'$' .$data_pag->cursors->after,
          'prevPage' => empty($old_feeds['pagination']['prevPage']) ? $data_pag->cursors->before : $old_feeds['pagination']['prevPage'].'$' .$data_pag->cursors->before,
          'resultsPerPage' => sizeof($feeds['data']),
          'totalResults' => sizeof($feeds['data'])
        );     
        break;
      case 'YouTube':
        foreach ($data->items as $key => $item) {
          if($item->id->kind == "youtube#video"){
            $date = new \DateTimeImmutable($item->snippet->publishedAt, new \DateTimeZone('UTC'));

            array_push($feeds['data'], array(
              'id' => $item->id->videoId,
              'title' => $item->snippet->title,
              'description' => $item->snippet->description,
              'social_network' => 'YouTube',
              'account' => isset($item->snippet->channelTitle) ? $item->snippet->channelTitle : 'Banrepcultural',
              'image' => $item->snippet->thumbnails->medium->url,
              'date' => $date->format('Y/m/d H:i:s'),
              'order' => $date->format('YmdHis'),
              'link' => 'https://www.youtube.com/watch?v='.$item->id->videoId,
              'icon' => 'icon-youtube-play',
              'text_length' => isset($item->description) ? strlen(strip_tags($item->description)) : 0
            ));
          }
        }
        $feeds['pagination'] = array(
          'nextPage' => $data->nextPageToken,
          'prevPage' => $data->prevPageToken,
          'resultsPerPage' => sizeof($feeds['data']),
          'totalResults' => sizeof($feeds['data'])
        );
        break;
    }
    return $feeds;
  }

  public function getInfoAccount($data, $social_network) {
    preg_match_all('#((https?|ftp)://(\S*?\.\S*?))([\s)\[\]{},;"\':<]|\.\s|$)#i', $data, $link);
    for ($i=0; $i < count($link[0]); $i++) { 
      $data = str_replace($link[0][$i], "<a href='".$link[0][$i]."' target='_blank'>".$link[0][$i]."</a>", $data);
    }
    preg_match_all('/@[A-Za-z0-9ñÑáéíóúÁÉÍÓÚ_-]+(?:|$)/', $data, $at);
    for ($i=0; $i < count($at[0]); $i++) {
        switch ($social_network) {
          case 'Twitter':
             $data = str_replace($at[0][$i], "<a href='https://twitter.com/".str_replace('@', '', $at[0][$i])."' target='_blank'>".$at[0][$i]."</a>", $data);
            break;
          case 'Instagram':
            $data = str_replace($at[0][$i], "<a href='https://www.instagram.com/".str_replace('@', '', $at[0][$i])."/' target='_blank'>".$at[0][$i]."</a>", $data);
            break;
        }
    }
    preg_match_all('/#[A-Za-z0-9ñÑáéíóúÁÉÍÓÚ\']+(?:|$)/', $data, $hashtag);
    for ($i=0; $i < count($hashtag[0]); $i++) {
        switch ($social_network) {
          case 'Facebook':
            $data = str_replace($hashtag[0][$i], "<a href='https://www.facebook.com/hashtag/".str_replace('#', '', $hashtag[0][$i])."' target='_blank'>".$hashtag[0][$i]."</a>", $data);
            break;
          case 'Twitter':
            $data = str_replace($hashtag[0][$i], "<a href='https://twitter.com/hashtag/".str_replace('#', '', $hashtag[0][$i])."?src=hash' target='_blank'>".$hashtag[0][$i]."</a>", $data);
            break;
          case 'Instagram':
            $data = str_replace($hashtag[0][$i], "<a href='https://www.instagram.com/explore/tags/".str_replace('#', '', $hashtag[0][$i])."/' target='_blank'>".$hashtag[0][$i]."</a>", $data);
            break;
        }
    }
    return $data;
  }

  public function arraySortBy(&$arrIni, $col, $order = SORT_ASC){
      $arrAux = array();
      foreach ($arrIni as $key=> $row)
      {
        $arrAux[$key] = is_object($row) ? $arrAux[$key] = $row->$col : $row[$col];
        $arrAux[$key] = strtolower($arrAux[$key]);
      }
      array_multisort($arrAux, $order, $arrIni);
      return $arrIni;
  }

  public function feedsJson(){
    $results = file_get_contents('sites/default/files/media/social_feeds.json');
    $results = json_decode($results, true);
    return new JsonResponse($results);
  }
}