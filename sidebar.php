  <aside class="main-sidebar sidebar-light-fuchsia elevation-4">
    <div class="dropdown">
   	<a href="./" class="brand-link">
        <h3 class="text-center p-0 m-0"><b>TodoPM</b></h3>
    </a>
      
    </div>
    <div class="sidebar pb-4 mb-4">
      <nav class="mt-2">
        <ul class="nav nav-pills nav-sidebar flex-column nav-flat" data-widget="treeview" role="menu" data-accordion="false">
          <li class="nav-item dropdown">
            <a href="./" class="nav-link nav-home">
              <i class="nav-icon fas fa-tachometer-alt"></i>
              <p>
                Dashboard
              </p>
            </a>
          </li>  
          <li class="nav-item">
            <a href="./index.php?page=project_list" class="nav-link nav-new_project nav-view_project nav-project_list">
              <i class="nav-icon fas fa-layer-group"></i>
              <p>
                Projects
              </p>
            </a>
          </li>
          <li class="nav-item">
                <a href="./index.php?page=task_list" class="nav-link nav-task_list">
                  <i class="fas fa-tasks nav-icon"></i>
                  <p>Task</p>
                </a>
          </li>

          <?php if($_SESSION['login_type'] == 1): ?>
          <li class="nav-item">
            <a href="./index.php?page=user_list" class="nav-link nav-edit_user nav-user_list">
              <i class="nav-icon fas fa-users"></i>
              <p>
                Users
              </p>
            </a>
          </li>
        <?php endif; ?>
        </ul>
      </nav>
    </div>
  </aside>
  <script>
  	$(document).ready(function(){
      var page = '<?php echo isset($_GET['page']) ? $_GET['page'] : 'home' ?>';
  		var s = '<?php echo isset($_GET['s']) ? $_GET['s'] : '' ?>';
      if(s!='')
        page = page+'_'+s;
  		if($('.nav-link.nav-'+page).length > 0){
             $('.nav-link.nav-'+page).addClass('active')
  			if($('.nav-link.nav-'+page).hasClass('tree-item') == true){
            $('.nav-link.nav-'+page).closest('.nav-treeview').siblings('a').addClass('active')
  				$('.nav-link.nav-'+page).closest('.nav-treeview').parent().addClass('menu-open')
  			}
        if($('.nav-link.nav-'+page).hasClass('nav-is-tree') == true){
          $('.nav-link.nav-'+page).parent().addClass('menu-open')
        }

  		}
     
  	})
  </script>