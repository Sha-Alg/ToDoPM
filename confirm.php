<?php
session_start();
include('./db_connect.php');
?>
<!DOCTYPE html>
<html lang="en">
<?php include 'header.php' ?>
<body class="hold-transition login-page  ">
<div class="login-box card card-outline">
    <div class="card-header card-title ">
        <b>ToDoPM - Confirm Email</b>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <form action="" id="confirm-form">
                <input type="hidden" name="email" value="<?php echo $_SESSION['email'] ?? '' ?>">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="code" required placeholder="Code">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-file-code"></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-8 ">
                    </div>
                    <div class="col-4">
                        <button type="submit" class="btn btn-primary btn-block">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    $(document).ready(function () {
        $('#confirm-form').submit(function (e) {
            e.preventDefault()
            start_load()
            if ($(this).find('.alert-danger').length > 0)
                $(this).find('.alert-danger').remove();
            $.ajax({
                url: 'ajax.php?action=confirm',
                method: 'POST',
                data: $(this).serialize(),
                error: err => {
                    console.log(err)
                    end_load();
                    $('#confirm-form').prepend('<div class="alert alert-danger">"+(err.responsJSON?.responseText)??err.responseText+"</div>')
                },
                success: function (resp) {
                    if (resp == 1) {
                        location.href = 'index.php?page=home';
                    } else {
                        $('#confirm-form').prepend('<div class="alert alert-danger">'+resp+'</div>')
                    }
                    end_load();
                }
            })
        })
    })
</script>
<?php include 'footer.php' ?>

</body>
</html>
