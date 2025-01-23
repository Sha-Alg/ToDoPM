<?php include 'db_connect.php' ?>
<div class="card card-outline card-success">
    <div class="card-header">
        <div class="card-tools">
            <a class="btn btn-block btn-sm btn-default btn-flat border-primary edit-user" href="#"><i
                        class="fa fa-plus"></i> Add New User</a>
        </div>
    </div>
    <div class="card-body">
        <table class="table tabe-hover table-bordered" id="list">
            <thead>
            <tr>
                <th class="text-center">#</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Action</th>
            </tr>
            </thead>
            <tbody>
            <?php
            $i = 1;
            $type = array('', "Admin", "Project Manager", "Employee");
            $qry = $conn->query("SELECT *,concat(firstname,' ',lastname) as name FROM users order by concat(firstname,' ',lastname) asc");
            while ($row = $qry->fetch_assoc()):
                ?>
                <tr>
                    <th class="text-center"><?php echo $i++ ?></th>
                    <td><b><?php echo ucwords($row['name']) ?></b></td>
                    <td><b><?php echo $row['email'] ?></b></td>
                    <td><b><?php echo $type[$row['type']] ?></b></td>
                    <td class="text-center btn-group">
                        <a class="btn btn-outline-primary edit-user" href="#" data-name="<?php echo $row['name'] ?>"
                           data-id="<?php echo $row['id'] ?>">Edit</a>
                        <a class="btn btn-outline-danger delete_user" href="javascript:void(0)"
                           data-id="<?php echo $row['id'] ?>">Delete</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
<script>
    $(document).ready(function () {
        $('#list').dataTable()
        $('.view_user').click(function () {
            uni_modal("<i class='fa fa-id-card'></i> User Details", "view_user.php?id=" + $(this).attr('data-id'))
        })
        $('.edit-user').click(function () {
            uni_modal("<i class='fa fa-edit'></i> User Info: " + ($(this).attr('data-name') ? '' : ''), "new_user.php?id=" + $(this).attr('data-id'), 'large')
        })
        $('.delete_user').click(function () {
            _conf("Are you sure to delete this user?", "delete_user", [$(this).attr('data-id')])
        })
    })

    function delete_user($id) {
        start_load()
        $.ajax({
            url: 'ajax.php?action=delete_user',
            method: 'POST',
            data: {id: $id},
            success: function (resp) {
                end_load()
                if (resp == 1) {
                    alert_toast("Data successfully deleted", 'success')
                    setTimeout(function () {
                        location.reload()
                    }, 1500)
                } else {
                    console.error(resp)
                    alert_toast(resp, 'error')
                }
            }
        })
    }
</script>