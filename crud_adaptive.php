<?php
require_once("crud_function.php");

class CRUD_Adaptive extends CRUD_Function {

   function modify_create_form($action,$fn=null)
   { 
        $this->log->debug("modify_create: ".$action);
        //if the keys were passed we know that an existing row is being modified
        $allkeys=true;
        foreach ($this->keys as $k)
        {
           if (!isset($_REQUEST["original_$k"])) $allkeys=false;
        }  
        if ($allkeys)
        {
            $sql = "select * from $this->tblname where ";
	        $sql .= $this->getWhere();
	        $this->log->debug($sql);
            $result = $this->conn->query($sql) or die("query '$sql' failed");
            $row=$result->fetch();
	    if ($action == "delete_confirm")
	    { 
            $newaction="delete"; 
		    echo "Are you sure?";
	    }else $newaction = "save";
        }else 
        {
            $row = array();
            foreach ($this->fields as $field)
               $row[$field]='';
	        $newaction = "add";
        }	  

?>
<style type="text/css">
   table {background-color:blue;border-radius:10px;box-shadow:10px 10px 5px gray}
   td {background-color:lightblue;padding-left:15px;padding-right:15px;border-radius:10px}
   th {color:white}
</style>
<form >
<?php foreach ($this->keys as $k) : ?>
<input type="hidden" name="original_<?php echo $k?>" value="<?php echo $row[$k]?>">
<?php endforeach ?>
<table>
   <tr><th colspan="2"><?php echo ucfirst($action) ?></th></tr>
      <?php foreach($this->fields as $field): ?>
   <tr>
      <td><?php echo $field?></td>
	  <td>
      <?php if ($newaction=='delete'): echo $row[$field];
      elseif (!empty($fn[$field])):
          $select = $fn[$field];
          $this->log->debug("Using function: $select with $field");
          $this->log->debug("function output: ".$select($this,$field));
          echo $select($this,$row);
      else :?>  
          <input name="<?php echo $field?>" value=" <?php echo $row[$field] ?>">
     <?php endif ?>
        </td>
   </tr>
      <?php endforeach ?>
   <tr>  
      <td colspan="2">
	    <input type="submit" name="action" value="<?php echo $newaction?>">
	  </td>
    </tr>		 
</table>
</form>
<?php 
   }#modify_create   
}
?>
