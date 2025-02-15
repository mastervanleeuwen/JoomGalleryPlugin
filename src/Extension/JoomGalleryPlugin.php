<?php
namespace My\Plugin\Content\Joomgallery\Extension;

// no direct access
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\Event;
use Joomla\Event\SubscriberInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Event\Result\ResultAwareInterface;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\Registry\Registry;

use Joomgallery\Component\Joomgallery\Administrator\Helper\JoomHelper;

class JoomGalleryPlugin extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
                'onContentPrepare' => 'replaceJGtags'  
                ];
    }

	function renderLinks(&$text)
	{
		$regex_link = '/href="joomgallery:([0-9]+)([a-z,A-Z,0-9,%,=,|, ]*)"/';
    	if(preg_match_all($regex_link, $text, $matches, PREG_SET_ORDER))
    	{
			foreach($matches as $match)
			{
            $output = 'href="'.JoomHelper::getViewRoute('image',$match[1]).'"';
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

	function renderTitles(&$text)
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

	public function renderImages(&$text)
	{
		$regex_tag  = '/{joomgallery:([0-9]+)(.*)}/';

		if(preg_match_all($regex_tag, $text, $matches, PREG_SET_ORDER))
		{
    		$params = ComponentHelper::getParams('com_joomgallery');
    		$caption_align = $params->get('jg_category_view_caption_align', 'center', 'STRING');

			foreach($matches as $match)
			{
				$type = 'detail';
				if (strpos($match[2], 'original')) $type = 'original';
				if (strpos($match[2], 'thumbnail')) $type = 'thumbnail';
        
				$imageurl = JoomHelper::getImg($match[1],$type);

				if(!is_null($imageurl))
				{
					// Linked
	            if(strpos($match[2], 'nolink') === false)
   	         {
      	          $linked = true;
         	   }
            	else
					{
						$linked = false;
					}

					$align = 'text-center center';
					if(strpos($match[2], 'right')) $align = 'right';
					if(strpos($match[2], 'left')) $align = 'left';
            
					$image = JoomHelper::getRecord('image',$match[1]);
					$output = "<figure class=\"figure joom-image $align\">.\n";
					$output .= '<img src="'.$imageurl.'" class="figure-img img-fluid rounded" alt="'.$image->title.'">'."\n";
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

	public function renderCat(&$text)
	{
		$regex_cat  = '/{joomgallerycat:([0-9]+)([a-z,0-9,=,",|, ]*)}/';

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
				$category_class   = $params['configs']->get('jg_category_view_class', 'masonry', 'STRING');;
				$num_columns      = $params['configs']->get('jg_category_view_num_columns', 6, 'INT');
				$caption_align    = $params['configs']->get('jg_category_view_caption_align', 'right', 'STRING');
				$image_class      = $params['configs']->get('jg_category_view_image_class', '', 'STRING');
				$justified_height = $params['configs']->get('jg_category_view_justified_height', 320, 'INT');
				$justified_gap    = $params['configs']->get('jg_category_view_justified_gap', 5, 'INT');
				$show_title       = $params['configs']->get('jg_category_view_images_show_title', 0, 'INT');
				$use_pagination   = $params['configs']->get('jg_category_view_pagination', 0, 'INT');
				$image_link       = $params['configs']->get('jg_category_view_image_link', 'defaultview', 'STRING');
				$title_link       = $params['configs']->get('jg_category_view_title_link', 'defaultview', 'STRING');
				$lightbox_image   = $params['configs']->get('jg_category_view_lightbox_image', 'detail', 'STRING');
				$show_description = $params['configs']->get('jg_category_view_show_description', 0, 'INT');
				$show_imgdate     = $params['configs']->get('jg_category_view_show_imgdate', 0, 'INT');
				$show_imgauthor   = $params['configs']->get('jg_category_view_show_imgauthor', 0, 'INT');
				$show_tags        = $params['configs']->get('jg_category_view_show_tags', 0, 'INT');

				$options = explode('|', $match[2]);
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
						$this->wa->useScript('com_joomgallery.lg-thumbnail');
						$this->wa->useStyle('com_joomgallery.lightgallery-bundle');
					}
	
					// Add and initialize the grid script
					$iniJS  = 'window.joomGrid = {';
					$iniJS .= '  itemid: ' . $catitem->id . ',';
					$iniJS .= '  pagination: ' . $use_pagination . ',';
					$iniJS .= '  layout: "' . $category_class . '",';
					$iniJS .= '  num_columns: ' . $num_columns . ',';
					$iniJS .= '  lightbox: ' . ($lightbox ? 'true' : 'false') . ',';
					$iniJS .= '  justified: {height: '.$justified_height.', gap: '.$justified_gap.'}';
					$iniJS .= '};';

					$this->wa->addInlineScript($iniJS, ['position' => 'before'], [], ['com_joomgallery.joomgrid']);
					$this->wa->useScript('com_joomgallery.joomgrid');

					$catimages = $catView->getModel()->getImages();
					$children = $catView->getModel()->getChildren();
					$imgsData = [ 'id' => (int) $catitem->id, 'layout' => $category_class, 'items' => $catimages, 'num_columns' => (int) $num_columns,
                  'caption_align' => $caption_align, 'image_class' => $image_class, 'image_type' => $lightbox_image, 'image_link' => $image_link,
                  'image_title' => (bool) $show_title, 'title_link' => $title_link, 'image_desc' => (bool) $show_description, 'image_date' => (bool) $show_imgdate,
                  'image_author' => (bool) $show_imgauthor, 'image_tags' => (bool) $show_tags
                ];
					$output = LayoutHelper::render('joomgallery.grids.images', $imgsData, null, array('component' => 'com_joomgallery'));
					$output .= "<script>\n".
						"  if(window.joomGrid.layout != 'justified') {\n".
						"    var loadImg = function() {\n".
						"      this.closest('.' + window.joomGrid.imgboxclass).classList.add('loaded');\n".
						"    }\n\n".
						"    let images = Array.from(document.getElementsByClassName(window.joomGrid.imgclass));\n".
						"    images.forEach(image => {\n".
						"      image.addEventListener('load', loadImg);\n".
						"    });\n".
						"  }\n".
						"</script>\n";
					/*
					$data = array('interface' => $this, 'item' => $catitem, 'images' => $catimages, 'children' => $children, 'category_class' => $category_class, 'image_class' => $image_class, 'num_columns' => $num_columns, 'caption_align' => $caption_align, 'lightbox' => $lightbox);
$layout = new FileLayout('category.thumbs', null, array('component' => 'com_joomgallery'));
					$output = $layout->render($data);
					*/
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
		$config = Factory::getApplication()->getConfig()->toArray();  // config params as an array
            // (we can't do a foreach over the config params as a Registry because they're protected)

		// Simple performance check to determine whether bot should process further
		if (strpos($article->text, 'joomgallery') === false)
		{
			return;
		}

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

		$this->renderImages($article->text);
		$this->renderCat($article->text);
		$this->renderTitles($article->text);
		$this->renderLinks($article->text);
	}
}
?>
