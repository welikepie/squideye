<?
  $curPageIndex = $pagination->getCurrentPageIndex();
  $pageNumber = $pagination->getPageCount();
  $suffix = isset($suffix) ? $suffix : null;
?>
  <div class="pagination">
  <p>
    Showing  <strong><?= ($pagination->getFirstPageRowIndex()+1).'-'.($pagination->getLastPageRowIndex()+1) ?> </strong>
    of <strong><?= $pagination->getRowCount() ?></strong> records. 

    Page: <span class="numbers">
    <? for ($i = 1; $i <= $pageNumber; $i++): ?>
      <? if ($i != $curPageIndex+1): ?><a href="<?= $base_url.'/'.$i.$suffix ?>"><? endif ?>
      <?= $i ?>
      <? if ($i != $curPageIndex+1): ?></a><? endif ?>
    <? endfor ?>
  </span></p>
  
  <p class="pages">

  <? if ($curPageIndex): ?><a href="<?= $base_url.'/'.$curPageIndex.$suffix ?>"><? endif ?>
  &#x2190; Previous page  
  <? if ($curPageIndex): ?></a><? endif ?>
  
  |

    <? if ($curPageIndex < $pageNumber-1): ?><a href="<?= $base_url.'/'.($curPageIndex+2).$suffix ?>"><? endif ?>
  Next page &#x2192;
  <? if ($curPageIndex < $pageNumber-1):  ?></a><? endif ?>

  </p></div>