Drupal 8 Drush 9 command for deleting huge amount of nodes using Batches and deleting their Content Type.


Beside main delete:allnodes command there are few helpers.
ddn:fields - for listing all fields and content types they belong too.
ddn:ct - for listing all content types and count of nodes per content type.


Example usage would be too compare all fields before and after nodes and Content Type deletion:
 
  drush ddn:fields > fields_before.txt
  drush ddn:ct > ct_count_before.txt
 
  drush delete:allnodes event
 
  drush ddn:fields > fields_after.txt
  drush ddn:ct > ct_count_after.txt
  
  vimdiff fields_before.txt fields_after.txt
  vimdiff ct_count_before.txt ct_count_after.txt
