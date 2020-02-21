For buttons and modals (inline help) add language strings following this format:
  $GROUP_$MODEOFVIEW_$HTML-REF_$ACTION_$SUBGROUP

############
SCHEMA
############

Part          | Example       | Explanation   
--------------|---------------|-------------------------------------------------
$GROUP        | loc           | All about locating stuff
              | ...           | Add groups in a way of naming topics
$MODEOFVIEW   | btn           | Displayed in or by hovering button
              | modal         | Display in a modal popup
$HTML-REF     | Hover         | Hover infos for buttons
              | Title         | Title of the modal
              | Body          | Part with (long) help text in the modal
              | Foot          | Something below the help text
$ACTION       | shelf         | As "Fetch from shelf"
              | sem           | As "Fetch from special shelf 'course reserve'"
              | eonly         | As "Just download"
              | order         | As "Order from closed stack"
              | reserve       | As "Place a hold/rerve lent item"
              | service       | As "Refer to service personal"
              | ...           | (Add more if applicable)
$SUBGROUP     | lbs           | "Lehrbuchsammlung on the first floor"
              | ls1           | "Regular shelf on the first floor"
              | ls2           | "Regular shelf on the second floor"
              | sem           | "Course reserve on the second floor"

############
CONSIDERATIONS
############
Most of the time you won't need all $HTML-REF, yet it might help to insert them
anyway in the language file and in the source code where it "would" be
appropriate. For now, just removing unused options


############
EXAMPLE
############

modal_loc_Title_shelf_ls1



