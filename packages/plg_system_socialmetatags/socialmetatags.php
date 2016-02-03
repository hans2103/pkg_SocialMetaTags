<?php
/**
 * @package     Social Meta Tags
 * @copyright   Copyright (c) Hans Kuijpers - HKweb
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

	// JFactory::getApplication();
	protected $app;

    function onAfterRoute()
    {
		if ($this->app->isAdmin())
		{
			return true;
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

		if (($this->app->get('gzip') == 1) && $unsupported)
		{
			$this->app->set('gzip', 0);
		}
    }

    public function onBeforeRender()
    {
        // Connect to Joomla
        $this->doc  = JFactory::getDocument();
        $this->lang = JFactory::getLanguage();

        // Don't run on Joomla backend
        if ($this->app->isAdmin())
        {
            return true;
        }

        // Don't execute on RSS feed
        if ($this->app->input->getCmd('format', '') == 'feed')
        {
            return true;
        }

        // Detecting Active Variables
        $sitename    = $this->app->getCfg('sitename');
        $description = $this->doc->getMetaData("description");
        $url         = JURI::current();
        $title       = htmlspecialchars(str_replace(' - '.$this->app->getCfg('sitename'), '', $this->doc->getTitle()));
        $menu        = $this->app->getMenu();

        // Get Plugin info
        $basicimage  = JURI::base() . $this->params->get('basicimage');
        $fbAdmin     = $this->params->get('fbadmin');
        $fbAppid     = $this->params->get('fbappid');
        $ogtype      = 'business.business';

        // Component specific overrides
        if ($this->app->input->get('option') == 'com_content' && $this->app->input->get('view') == 'article')
        {
			// Get information of current article and set og:type
			$article            = JTable::getInstance("content");
            $article->load($this->app->input->get('id'));
			$images             = json_decode($article->images);
			$ogtype             = 'article';

			// Get profile and user information
			$profile            = JUserHelper::getProfile($article->created_by);
            $user               = JFactory::getUser($article->created_by);
            $realname           = $user->name;

			// If the article has a introtext, use it as description
            if (!empty($article->introtext))
            {
				$description = preg_replace('/{[\s\S]+?}/', '', trim(htmlspecialchars(strip_tags($article->introtext))));
				$description = preg_replace('/\s\s+/', ' ', $description);
            }

			// Set Twitter description
			$descriptiontw = JHtml::_('string.truncate', $description, 140);

			// Set facebook descriptoin
			$descriptionfb = JHtml::_('string.truncate', $description, 300);

			// Set general descripton tag
			$description = JHtml::_('string.truncate', $description, 160);
			$this->doc->setDescription($description);

			// Get canonical url if exist
            foreach ($this->doc->_links as $l => $array)
			{
				if ($array['relation'] == 'canonical')
				{
					$url = $this->doc->_links[$l];
				}
            }

			// Set social image
			if (!empty($images->image_fulltext))
            {
				$basicimage = JURI::base() . $images->image_fulltext;
            }
            elseif (!empty($images->image_intro))
            {
				$basicimage = JURI::base() . $images->image_intro;
            }
            elseif (strpos($article->fulltext, '<img') !== false)
            {
				// Get img tag from article
				preg_match('/(?<!_)src=([\'"])?(.*?)\\1/', $article->fulltext, $articleimages);
				$basicimage = JURI::base() . $articleimages[2];
            }
			elseif (strpos($article->introtext, '<img') !== false)
            {
				// Get img tag from article
				preg_match('/(?<!_)src=([\'"])?(.*?)\\1/', $article->introtext, $articleimages);
				$basicimage = JURI::base() . $articleimages[2];
            }

			// Set publish and modifed time
            $publishedtime = $article->created;
            $modifiedtime  = $article->modified;

			// Set Profile information
			$profile_googleplus = $profile->socialmetatags['googleplus'];
			$profile_twitter    = $profile->socialmetatags['twitter'];
			$profile_facebook   = $profile->socialmetatags['facebook'];
        }
		else
		{
			// Fallback
			$descriptiontw      = JHtml::_('string.truncate', $description, 140);
			$descriptionfb      = JHtml::_('string.truncate', $description, 300);
			$profile_googleplus = $this->params->get('googlepluspublisher');
			$profile_twitter    = $this->params->get('twittersite');
		}

        // Set Meta Tags
        $metaproperty = array();
        $metaitemprop = array();
        $metaname     = array();

        // Meta Tags for Discoverability
        $metaproperty['place:location:latitude']              = $this->params->get('placelocationlatitude');
        $metaproperty['place:location:longitude']             = $this->params->get('placelocationlongitude');
        $metaproperty['business:contact_data:street_address'] = $this->params->get('businesscontentdatastreetaddress');
        $metaproperty['business:contact_data:locality']       = $this->params->get('businesscontentdatalocality');
        $metaproperty['business:contact_data:postal_code']    = $this->params->get('businesscontentdatapostalcode');
        $metaproperty['business:contact_data:country_name']   = $this->params->get('businesscontentdatacountryname');
        $metaproperty['business:contact_data:email']          = $this->params->get('businesscontentdataemail');
        $metaproperty['business:contact_data:phone_number']   = $this->params->get('businesscontentdataphonenumber');
        $metaproperty['business:contact_data:website']        = $this->params->get('businesscontactdatawebsite');

        // Meta Tags for Google Plus
        $metaitemprop['name']        = $title;
        $metaitemprop['description'] = $description;
        $metaitemprop['image']       = $basicimage;

        if ($this->params->get('googlepluspublisher'))
        {
            $this->doc->addHeadLink($this->params->get('googlepluspublisher'), 'publisher', 'rel');
        }
        if (!empty($profile_googleplus))
        {
            $this->doc->addHeadLink($profile_googleplus, 'author', 'rel');
        }

        // Meta Tags for Twitter
        $metaname['twitter:card']           = 'summary_large_image';
        $metaname['twitter:site']           = $this->params->get('twittersite');
        $metaname['twitter:creator']        = $profile_twitter;
        $metaname['twitter:title']          = $title;
        $metaname['twitter:description']    = $descriptiontw;
        $metaname['twitter:image:src']      = $basicimage;

        // Meta Tags for Facebook
        // required
        $metaproperty['og:title']           = $title;
        $metaproperty['og:type']            = $ogtype;
        $metaproperty['og:image']           = $basicimage;
        $metaproperty['og:url']             = $url;
        $metaproperty['og:locale']          = str_replace('-','_',$this->lang->getTag());
		// optional
        $metaproperty['og:site_name']       = $sitename;
        $metaproperty['profile:first_name'] = ''; // By default Joomla has just one field for name
		$metaproperty['og:description']     = $descriptionfb;
        $metaproperty['og:see_also']        = JURI::base();

		if (isset($realname))
		{
			$metaproperty['profile:last_name'] = $realname;
		}

		if (isset($profile_facebook))
		{
			$metaproperty['profile:username'] = $profile_facebook;
		}

        if (isset($fbAdmin))
        {
			$metaproperty['fb:admins'] = $fbAdmin;
        }

        if (isset($fbAppid))
        {
			$metaproperty['fb:app_id'] = $fbAppid;
        }

		if (isset($publishedtime))
		{
        	$metaproperty['article:published_time'] = $publishedtime;
		}

		if (isset($modifiedtime))
		{
        	$metaproperty['article:modified_time'] = $modifiedtime;
		}

		if (isset($modifiedtime))
		{
        	$metaproperty['og:updated_time'] = $modifiedtime;
		}

		// Set mateproperty tags
        foreach ($metaproperty as $key => $value)
        {
            if ($value)
            {
                $this->doc->addCustomTag('<meta property="'.$key.'" content="'.$value.'" />');
            }
        }

		// Set itemprp tags
        foreach ($metaitemprop as $key => $value)
        {
            if ($value)
            {
                $this->doc->addCustomTag('<meta itemprop="'.$key.'" content="'.$value.'" />');
            }
        }

		// Set metaname tags
        foreach ($metaname as $key => $value)
        {
            if ($value)
            {
                $this->doc->setMetaData($key,$value);
            }
        }
    }
}
