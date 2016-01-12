<?php
/**
 * @package     Social Meta Tags
 * @copyright   Copyright (c) 2014 Hans Kuijpers - HKweb
 * @license     GNU General Public License version 3 or later
 */

// No direct access.
defined('_JEXEC') or die;

class PlgSystemSocialmetatags extends JPlugin
{
    /**
     * Load the language file on instantiation. Note this is only available in Joomla 3.1 and higher.
     * If you want to support 3.0 series you must override the constructor
     *
     * @var    boolean
     * @since  3.1
     */
    protected $autoloadLanguage = true;

    function onAfterRoute()
    {
      $app = JFactory::getApplication();

      if ( $app->isAdmin() )
      {
        return;
      }
      $unsupported = false;

      if (isset($_SERVER['HTTP_USER_AGENT']))
      {
        /* Facebook User Agent
        * facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)
        * LinkedIn User Agent
        * LinkedInBot/1.0 (compatible; Mozilla/5.0; Jakarta Commons-HttpClient/3.1 +http://www.linkedin.com)
        */
        $pattern = strtolower('/facebookexternalhit|LinkedInBot/x');
        if (preg_match($pattern, strtolower($_SERVER['HTTP_USER_AGENT'])))
        {
          $unsupported = true;
        }
      }

      if (($app->get('gzip') == 1) && $unsupported)
      {
        $app->set('gzip', 0);
      }
    }

    public function onBeforeRender()
    {
        // Connect to Joomla
        $app            = JFactory::getApplication();
        $doc            = JFactory::getDocument();
        $option         = $app->input->getCmd('option', '');
        $view           = $app->input->getCmd('view', '');
        $id             = $app->input->getCmd('id', '');
        $format         = $app->input->getCmd('format', '');

        // Don't run on Joomla backend
        if ($app->isAdmin())
        {
            return;
        }

        // Don't execute on RSS feed
        if ($format == 'feed')
        {
            return;
        }

        // Detecting Active Variables
        $sitename       = $app->getCfg('sitename');
        $description    = $doc->getDescription();
        $url_site       = JURI::base();
        $url            = JURI::current();
        $title          = str_replace(' - '.$app->getCfg('sitename'), '', $doc->getTitle());
        $menu           = $app->getMenu();

        // Get Plugin info
        $basicimage     = $this->params->get('basicimage');
        $ogtype         = 'business.business';

        // Component specific overrides
        if ($menu->getActive() == $menu->getDefault())
        {
            // overrides for homepage
        }
        elseif ($option == 'com_content' && $view == 'article')
        {
            $article            = JTable::getInstance("content");
            $article->load($id);

            $profile            = JUserHelper::getProfile($article->created_by);
            $user               = JFactory::getUser($article->created_by);
            $realname           = $user->name;

            $descriptiontw      ='';
            $descriptionfb      ='';

            if(!$description)
            {
                $description    = trim(htmlspecialchars(strip_tags($article->introtext)));
                $descriptiontw  = substr($descriptiontw, 0, 160);
                $doc->setDescription($description);
            }

            if (!$descriptiontw)
            {
              $descriptiontw    = trim(htmlspecialchars(strip_tags($article->introtext)));
              $descriptiontw    = substr($descriptiontw, 0, 140);
            }

            if (!$descriptionfb)
            {
              $descriptionfb    = trim(htmlspecialchars(strip_tags($article->introtext)));
              $descriptionfb    = substr($descriptionfb, 0, 300);
            }

            foreach ($doc->_links as $l => $array) {
              if ($array['relation'] == 'canonical') {
                $url            = $doc->_links[$l]; // get canonical url if exist
              }
            }
            $ogtype             = 'article';

            $images = json_decode($article->images);
            if($images->image_fulltext)
            {
                $basicimage = $url_site.$images->image_fulltext;
            }
            elseif ($images->image_intro)
            {
                $basicimage = $url_site.$images->image_intro;
            }

            $publishedtime  = $article->created;
            $modifiedtime  = $article->modified;
        }

        // Set Meta Tags
        $metaproperty   = array();
        $metaitemprop   = array();
        $metaname       = array();

        // Meta Tags for Discoverability
        $metaproperty['place:location:latitude'] = $this->params->get('placelocationlatitude');
        $metaproperty['place:location:longitude'] = $this->params->get('placelocationlongitude');
        $metaproperty['business:contact_data:street_address'] = $this->params->get('businesscontentdatastreetaddress');
        $metaproperty['business:contact_data:locality'] = $this->params->get('businesscontentdatalocality');
        $metaproperty['business:contact_data:postal_code'] = $this->params->get('businesscontentdatapostalcode');
        $metaproperty['business:contact_data:country_name'] = $this->params->get('businesscontentdatacountryname');
        $metaproperty['business:contact_data:email'] = $this->params->get('businesscontentdataemail');
        $metaproperty['business:contact_data:phone_number'] = $this->params->get('businesscontentdataphonenumber');
        $metaproperty['business:contact_data:website'] = $this->params->get('businesscontactdatawebsite');

        // Meta Tags for Google Plus
        $metaitemprop['name'] = $title;
        $metaitemprop['description'] = $description;
        $metaitemprop['image'] = $basicimage;
        if($this->params->get('googlepluspublisher')) {
            $doc->addHeadLink($this->params->get('googlepluspublisher'), 'publisher', 'rel');
        }
        if($profile->socialmetatags['googleplus']) {
            $doc->addHeadLink($profile->socialmetatags['googleplus'], 'author', 'rel');
        }

        // Meta Tags for Twitter
        $metaname['twitter:card'] = 'summary_large_image';
        $metaname['twitter:site'] = $this->params->get('twittersite');
        $metaname['twitter:creator'] = $profile->socialmetatags['twitter'];
        $metaname['twitter:title'] = $title;
        $metaname['twitter:description'] = $descriptiontw;
        $metaname['twitter:image:src'] = $basicimage;

        // Meta Tags for Facebook
        $metaproperty['og:title'] = $title;
        $metaproperty['og:type'] = $ogtype;
        $metaproperty['og:image'] = $basicimage;
        $metaproperty['og:url'] = $url;
        $metaproperty['og:site_name'] = $sitename;
        $metaproperty['profile:first_name'] = ''; // By default Joomla has just one field for name
        $metaproperty['profile:last_name'] = $realname;
        $metaproperty['profile:username'] = $profile->socialmetatags['facebook'];
        $metaproperty['og:description'] = $descriptionfb;
        $metaproperty['og:see_also'] = $url_site;

        if($this->params->get('fbadmin'))
        {
          $metaproperty['fb:admins'] = $this->params->get('fbadmin');
        }

        if($this->params->get('fbappid'))
        {
          $metaproperty['fb:app_id'] = $this->params->get('fbappid');
        }

        $metaproperty['article:published_time'] = $publishedtime;
        $metaproperty['article:modified_time'] = $modifiedtime;
        $metaproperty['og:updated_time'] = $modifiedtime;

        foreach ($metaproperty as $key => $value)
        {
            if ($value)
            {
                $doc->addCustomTag('<meta property="'.$key.'" content="'.$value.'" />');
            }
        }

        foreach ($metaitemprop as $key => $value)
        {
            if ($value)
            {
                $doc->addCustomTag('<meta itemprop="'.$key.'" content="'.$value.'" />');
            }
        }

        foreach ($metaname as $key => $value)
        {
            if ($value)
            {
                $doc->setMetaData($key,$value);
            }
        }
    }
}
