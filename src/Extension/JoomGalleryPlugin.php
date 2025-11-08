<?php
namespace My\Plugin\Content\Joomgallery\Extension;

// no direct access
defined('_JEXEC') or die;

use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Registry\Registry;

use Joomgallery\Component\Joomgallery\Administrator\Helper\JoomHelper;

class JoomGalleryPlugin extends CMSPlugin implements SubscriberInterface
{
    protected $wa = false; 

    public static function getSubscribedEvents(): array
    {
        return [
                'onContentPrepare' => 'replaceJGtags'  
                ];
    }

	function renderLinks(&$text, $accept_legacy_tags)
	{
		$regex_link = '/href="joomgallery:([0-9]+)([a-z,A-Z,0-9,%,=,|, ]*)"/';
		if ($accept_legacy_tags) {
			$regex_link = '/href="(?:joomgallery|joomplulink):([0-9]+)([a-z,A-Z,0-9,%,=,|, ]*)"/';
		}
    	if(preg_match_all($regex_link, $text, $matches, PREG_SET_ORDER))
    	{
			foreach($matches as $match)
			{
				$type = 'image';
				$options = explode('|', trim($match[2]));
            foreach ($options as $option) {
               $opt = explode('=',$option);
               if ($opt[0]=='type') {
						$type = $opt[1];
						if ($type == 'img') $type = 'image';
						if ($type == 'thumb') $type = 'thumbnail';
					}
					if ($opt[0]=='view' && $opt[1]=='category') $type = 'category';
				}
				$output = 'href="'.JoomHelper::getViewRoute($type,$match[1]).'"';
				$text = str_replace($match[0], $output, $text);
			}
		}
		$regex_catlink = '/href="joomgallerycat:([0-9]+)"/';
    	if(preg_match_all($regex_catlink, $text, $matches, PREG_SET_ORDER))
    	{
			foreach($matches as $match)
			{
            $output = 'href="'.JoomHelper::getViewRoute('category',$match[1]).'"';
        		$text = str_replace($match[0], $output, $text);
			}
		}
	}

	function renderTitles(&$text, $accept_legacy_tags)
	{
		$regex_alt  = '/alt="joomgallery:([0-9]+)"/';

		if (preg_match_all($regex_alt, $text, $matches, PREG_SET_ORDER))
		{
			foreach($matches as $match)
			{
				$image = JoomHelper::getRecord('image',$match[1]);
				if(!is_null($image))
				{
					$output = 'alt="'.$image->title.'"';
				}
				else
				{
					$output = 'alt="'.Text::_('PLG_JGAL_IMAGE_NOT_DISPLAYABLE').'"';
				}
				$text = str_replace($match[0], $output, $text);
			}
		}
	}

	public function renderImages(&$text, $accept_legacy_tags)
	{
		$regex_tag  = '/{joomgallery:([0-9]+)([a-z,A-Z,0-9,%,=,|, ]*)}/';

		if ($accept_legacy_tags) {
			$regex_tag = '/{(?:joomgallery|joomplu):([0-9]+)([a-z,A-Z,0-9,%,=,|, ]*)}/';
		}

		if(preg_match_all($regex_tag, $text, $matches, PREG_SET_ORDER))
		{
    		$params = ComponentHelper::getParams('com_joomgallery');
    		$caption_align = $params->get('jg_category_view_caption_align', 'center', 'STRING');
			$align = '';

			foreach($matches as $match)
			{
				$type = 'detail';
				$catlink = false;
				$linked = true;
        
				try {
					$image = JoomHelper::getRecord('image',$match[1]);
				} catch (Exeption $e) {
					Factory::getApplication()->enqueueMessage($e->getMessage(),'error');
				}

				if($image)
				{
					$figclass = 'joom-image text-center';
					$floatfig = true;
					if (strpos($match[2], 'nolink'))
					{
						$linked = false;
					}
					$options = explode('|', trim($match[2]));
					foreach ($options as $option) {
						$opt = explode('=',$option);
						if ($opt[0]=='type') {
							$type = $opt[1];
							if ($type == 'img') $type = 'image';
							if ($type == 'orig') $type = 'original';
							if ($type == 'thumb') $type = 'thumbnail';
						}
						if ($opt[0]=='linked' && $opt[1]=='0') $linked=false;
						if ($opt[0]=='catlink' && $opt[1]) $catlink=true;
						if ($opt[0]=='float' && $opt[1]=='0') $floatfig=false;
						if ($opt[0]=='align' && $opt[1]) $align=$opt[1];
					}
					if ($floatfig) {
						if ($align=='left') $figclass = 'jg-image float-start';
						if ($align=='right') $figclass = 'jg-image float-end';
					}
					else {
						if ($opt[1]=='left') $figclass='joom-image text-start';
						if ($opt[1]=='right') $figclass='joom-image text-end';
					}
            
					$imageurl = JoomHelper::getImg($match[1],$type);
					// TODO: add catlink if requested
					// NB: joom-image has width:100% which prevents floating; check other classes?
					$output = "<figure class=\"figure $figclass\">.\n";
					// Try this instead:  Route::_('index.php?option=com_joomgallery&view=image&id='.(int) $match[1])
					if ($linked) $output .= '<a href="'.JoomHelper::getImg($match[1],'detail').'">';
					$output .= '<img src="'.$imageurl.'" class="figure-img img-fluid rounded" alt="'.$image->title.'">'."\n";
					if ($linked) $output .= '</a>';
					if (strpos($match[2], 'caption')) $output .= '<figcaption class="figure-caption '.$caption_align.'">'."{$image->title}</figcaption>\n";
					$output .= "</figure>\n";
				}
				else
				{
					$output = '<p><b>'.Text::_('PLG_JGAL_IMAGE_NOT_DISPLAYABLE').'</b></p>';
				}

				$text = str_replace($match[0], $output, $text);
			}
		}
	}

	public function renderCat(&$text, $accept_legacy_tags)
	{
		$regex_cat  = '/{joomgallerycat:([0-9]+)([a-z,0-9,=,",|, ]*)}/';

		if ($accept_legacy_tags) {
			$regex_cat  = '/{(?:joomgallerycat|joomplucat):([0-9]+)([a-z,0-9,=,",|, ]*)}/';
		}

		if(preg_match_all($regex_cat, $text, $matches, PREG_SET_ORDER))
		{
			foreach($matches as $match)
			{
				$app = \Joomla\CMS\Factory::getApplication();
				$joomgallery = $app->bootComponent('com_joomgallery')->getMVCFactory();

				$catModel = $joomgallery->createModel('Category','site');
				$catView = $joomgallery->createView('Category','Site','Html');
				$catView->setModel($catModel,true);
				$params = $catView->get('Params');
				// Subcategory params
				$subcategory_class          = $params['configs']->get('jg_category_view_subcategory_class', 'masonry', 'STRING');

				// Image params
				$category_class   = $params['configs']->get('jg_category_view_class', 'masonry', 'STRING');;
				$num_columns      = $params['configs']->get('jg_category_view_num_columns', 6, 'INT');
				$image_type       = $params['configs']->get('jg_category_view_type_images', 'thumbnail', 'STRING');
				$caption_align    = $params['configs']->get('jg_category_view_caption_align', 'right', 'STRING');
				$image_class      = $params['configs']->get('jg_category_view_image_class', '', 'STRING');
				$justified_height = $params['configs']->get('jg_category_view_justified_height', 320, 'INT');
				$justified_gap    = $params['configs']->get('jg_category_view_justified_gap', 5, 'INT');
				$show_title       = $params['configs']->get('jg_category_view_images_show_title', 0, 'INT');
				$use_pagination   = $params['configs']->get('jg_category_view_pagination', 0, 'INT');
				$reloaded_images  = $params['configs']->get('jg_category_view_number_of_reloaded_images', 3, 'INT');
				$image_link       = $params['configs']->get('jg_category_view_image_link', 'defaultview', 'STRING');
				$title_link       = $params['configs']->get('jg_category_view_title_link', 'defaultview', 'STRING');
				$lightbox_image   = $params['configs']->get('jg_category_view_lightbox_image', 'detail', 'STRING');
				$lightbox_thumbnails = $params['configs']->get('jg_lightbox_thumbnails', 0, 'INT');
				$lightbox_zoom       = $params['configs']->get('jg_lightbox_zoom', 0, 'INT');
				$show_description = $params['configs']->get('jg_category_view_show_description', 0, 'INT');
				$show_imgdate     = $params['configs']->get('jg_category_view_show_imgdate', 0, 'INT');
				$show_imgauthor   = $params['configs']->get('jg_category_view_show_imgauthor', 0, 'INT');
				$show_tags        = $params['configs']->get('jg_category_view_show_tags', 0, 'INT');

				$options = explode('|', trim($match[2]));
				$max_entries = 0;
				foreach ($options as $option) {
					$opt = explode('=',$option);
					if ($opt[0]=='columns') $num_columns=$opt[1];
					if ($opt[0]=='limit') $max_entries=$opt[1];
				}
				$catView->getModel()->getItem($match[1]);

				if (!is_null($catitem = $catView->getModel()->item))
				{ 
					// Import CSS & JS
					if($subcategory_class == 'masonry' || $category_class == 'masonry')
					{
						$this->wa->useScript('com_joomgallery.masonry');
					}

					if($category_class == 'justified')
					{
						$this->wa->useScript('com_joomgallery.justified');
						$this->wa->addInlineStyle('.jg-images[class*=" justified-"] .jg-image-caption-hover { right: ' . $justified_gap . 'px; }');
					}

					$lightbox = false;
					if($image_link == 'lightgallery' || $title_link == 'lightgallery')
					{
						$lightbox = true;

						$this->wa->useScript('com_joomgallery.lightgallery');
						$this->wa->useScript('com_joomgallery.lg-hash');
						$this->wa->useScript('com_joomgallery.lg-thumbnail');
						$this->wa->useScript('com_joomgallery.lg-zoom');
						$this->wa->useStyle('com_joomgallery.lightgallery-bundle');
					}

					$catimages = $catView->getModel()->getImages();
					if ($max_entries != 0) $catimages = array_slice($catimages, 0, $max_entries);
					$numb_images = count($catimages);

					// Add and initialize the grid script
					$iniJS  = 'window.joomGrid["1-'.$catitem->id.'"] = {';
					$iniJS .= '  itemid: "1-' . $catitem->id . '",';
					$iniJS .= '  pagination: ' . $use_pagination . ',';
					$iniJS .= '  layout: "' . $category_class . '",';
					$iniJS .= '  num_columns: ' . $num_columns . ',';
					$iniJS .= '  numb_images: ' . $numb_images . ',';
					$iniJS .= '  reloaded_images: ' . $reloaded_images . ',';
					$iniJS .= '  lightbox: ' . ($lightbox ? 'true' : 'false') . ',';
					$iniJS .= '  lightbox_params: {container: "lightgallery-1-'.$catitem->id.'", selector: ".lightgallery-item"},';
					$iniJS .= '  thumbnails: ' . ($lightbox_thumbnails ? 'true' : 'false') . ',';
					$iniJS .= '  zoom: ' . ($lightbox_zoom ? 'true' : 'false') . ',';
					$iniJS .= '  justified: {height: '.$justified_height.', gap: '.$justified_gap.'}';
					$iniJS .= '};';

					$this->wa->useScript('com_joomgallery.joomgrid');
					$this->wa->addInlineScript($iniJS, ['position' => 'after'], [], ['com_joomgallery.joomgrid']);

					$children = $catView->getModel()->getChildren();
					$imgsData = [ 'id' => '1-'.(int) $catitem->id, 'layout' => $category_class, 'items' => $catimages, 'num_columns' => (int) $num_columns,
                  'caption_align' => $caption_align, 'image_class' => $image_class, 'image_type' => $image_type, 'lightbox_type' => $lightbox_image, 'image_link' => $image_link,
                  'image_title' => (bool) $show_title, 'title_link' => $title_link, 'image_desc' => (bool) $show_description, 'image_date' => (bool) $show_imgdate,
                  'image_author' => (bool) $show_imgauthor, 'image_tags' => (bool) $show_tags
                ];
					$output = LayoutHelper::render('joomgallery.grids.images', $imgsData, null, array('component' => 'com_joomgallery'));
					$output .= "<script>\n".
						"  // Add event listener to images\n".
						"  let loadImg = function() {\n".
						"    this.closest('.jg-image').classList.add('loaded');\n".
						"  }\n".
						"  let images = Array.from(document.getElementsByClassName('jg-image-thumb'));\n".
						"  images.forEach(image => {\n".
						"    image.addEventListener('load', loadImg);\n".
						"  });\n".
						"</script>\n";
				}
				else
				{
					$output = Text::_('PLG_JGAL_CAT_NOT_FOUND');
				}
				$text = str_replace($match[0], $output, $text);
			}
		}
	}

	public function replaceJGtags(Event $event)
	{
		if (!$this->getApplication()->isClient('site')) {
			return;
		}
         
		[$context, $article, $params, $page] = array_values($event->getArguments());
		//if ($context !== "com_content.article" && $context !== "com_content.featured") return;
        
		$text = $article->text; // text of the article

		if (strpos($article->text, 'joomgallery') === false && strpos($article->text, 'joomplu') === false)
		{
			return;
		}

		$language = Factory::getApplication()->getLanguage();
		$language->load('com_joomgallery', JPATH_BASE . '/components/com_joomgallery');
		$language->load('JoomGalleryPlugin', JPATH_BASE . '/plugins/content/joomgallery');

		// Check existence of JoomGallery and include the interface class
		if(!\Joomla\CMS\Component\ComponentHelper::isEnabled('com_joomgallery'))
		{
			$output = '<p><b>'.JText::_('PLG_JGAL_JG_NOT_INSTALLED').'</b></p>';
			$article->text  = $output.$article->text;

			return;
		}
   
		$app = Factory::getApplication();
		$this->wa = $app->getDocument()->getWebAssetManager();
		$this->wa->getRegistry()->addExtensionRegistryFile('com_joomgallery');
		$this->wa->useStyle('com_joomgallery.site');
		$this->wa->useStyle('com_joomgallery.jg-icon-font');

		$legacy_tags = 0;
		if ($this->params->get('accept_legacy_tags')) $legacy_tags = 1;
		$this->renderImages($article->text, $legacy_tags);
		$this->renderCat($article->text, $legacy_tags);
		$this->renderTitles($article->text, $legacy_tags);
		$this->renderLinks($article->text, $legacy_tags);
	}
}
?>
