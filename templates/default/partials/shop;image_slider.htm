<? 
  $image_num = $images->count;
  
  if ($image_num):
    $image_width = 200;
    $image_height = 200;
    
    $large_image_width = 400;
    $large_image_height = 400;

    $thumb_url = $images[0]->getThumbnailPath($image_width, $image_height);
    $fullsize_url = $images[0]->getThumbnailPath($large_image_width, $large_image_height);
    
    $thumbnails = array();
    $fullsize = array();
    for ($i = 0; $i < $image_num; $i++)
    {
      $thumbnails[] = "'".$images[$i]->getThumbnailPath($image_width, $image_height)."'";
      $fullsize[] = "'".$images[$i]->getThumbnailPath($large_image_width, $large_image_height)."'";
    }
      
?>
    <div class="image" id="<?= $slider_id = 'slider'.uniqid() ?>">
      <div class="container">
        <a <? if ($image_num == 1): ?>rel="lightbox"<? endif ?> href="<?= $fullsize_url ?>"><img src="<?= $thumb_url ?>" alt=""/></a>
      </div>
      <p>Image <span>1</span> of <?= $image_num ?>. Click image to enlarge.</p>
      
      <? if ($image_num > 1): ?>
      <div class="slider">
        <div class="knob"></div>
        <div class="right"></div>
      </div>
      <? endif ?>
    </div>
    
    <? if ($image_num > 1): ?>
    <script type="text/javascript">
      new ImageSlider('<?= $slider_id ?>', [<?= implode(",", $thumbnails) ?>], [<?= implode(",", $fullsize) ?>]);
    </script>
    <? elseif (post('ls_session_key')): ?>
      <script type="text/javascript">
        (function(){ Slimbox.scanPage(); }).delay(250);
      </script>
    <? endif ?>
<? endif ?>