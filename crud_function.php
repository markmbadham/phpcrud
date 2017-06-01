<?php
require_once("crud.php");



class CRUD_Function extends crud {
	public $addcols=array();
	
   function html_output($fields,$result,$lines)
   {

?>     
<html>
   <head>
      <link rel ="stylesheet" type="text/css" href="reports/leads.css" />
      <link rel ="stylesheet" type="text/scs" href="jquery-ui/jquery-ui.min.css" />
      <script type="text/javascript" src="jquery-1.8.3.min.js"></script>
      <script type="text/javascript" src="jquery-ui-1.9.2.custom.min.js"></script>
      <script type="text/javascript" src="functions.js"></script>
   </head>
   <body>
	<form>
	<?php /* foreach ($this->keys as $k) : ?>
	   <input type="hidden" name="original_<?php echo $k?>" value="<?php echo $row[$k]?>">
	<?php endforeach */ ?>

	   <table>
	      <tr>
                  <th>Action</th>
	      <?php
     		foreach ($fields as $field): 
     
        	   $sort_checked=(isset($_REQUEST['sort']) && $_REQUEST['sort']==$field)?"checked":"";
                   $show_checked=(isset($_REQUEST['show']) && in_array($field,$_REQUEST['show']))?"checked":"";
              ?>  
                  <th><?php echo $field?>
		       <input type="radio" name="sort" value="<?php echo $field ?>" <?php echo $sort_checked?>>
                       <input type="checkbox"  name="show[]" value="<?php echo $field ?>" <?php echo $show_checked?>>
                   </th>
     	       <?php endforeach ?>
	      
	      </tr>
              <!-- filter row -->
              <tr>
                 <td><input type="submit" value="filter"></td>
                <?php foreach ($fields as $field):
					if (preg_match("/id$/i",$field))
						$size=5;
					elseif (preg_match("/(debit|credit|amount|balance)/i",$field))
						$size=9;
					elseif (preg_match("/(notes|comments)/i",$field))
						$size=35;
					else
						$size=15;
                ?> 
                  <td class="filter"><input size="<?php echo $size?>" name="<?php echo "filter_$field"?>" <?php if (!empty($_REQUEST["filter_$field"])):?> value="<?php echo $_REQUEST["filter_$field"]?>"
                      <?php endif ?>>
		  </td>
		<?php endforeach ?>  
	     </tr>

 
      <?php while ($row = $result->fetch()):?>
            <tr>
                 <td class="control"><a href="<?php echo $_SERVER['PHP_SELF']?>?action=modify<?php foreach ($this->keys as $k) echo "&original_$k={$row[$k]}";
	      echo '">Modify</a> | '.
		   '<a href ="'.$_SERVER['PHP_SELF'].
		   '?action=delete_confirm';
	      foreach ($this->keys as $k)
	           echo "&original_$k={$row[$k]}";	   
	      echo '">Delete</a></td>'?> 
	     <?php foreach ($fields as $field) :?>
                 <td><div class="cell"><div class="text"><?php 
					echo array_key_exists($field,$this->addcols) ? $this->addcols[$field]($this,$row,$field) : $row[$field];
				 ?></div></div></td>
             <?php endforeach?>


             </tr>
      <?php endwhile?>
             <tr>
	         <td colspan="<?php echo (count($fields)+1)?>" class="control" id="bottom">
		      <a href="<?php echo $_SERVER['PHP_SELF']?>?action=create">Add New</a> : 
		      Show <input size="3" name="lines" value="<?php echo $lines?>">  
                      lines<input type="submit" value="filter">
                 <input type="submit" name='action' value="next">
			<input type="hidden" name="offset" value="<?php echo $this->offset?>">
                      <input type="submit" name="format" value="csv">
		 </td>
	      </tr>	 
           </table>
    </form>
    <?php
  } #function html_output

}

?>
