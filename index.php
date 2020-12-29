<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reportes Diarios</title>
    <!-- Latest compiled and minified CSS -->
    <link rel="stylesheet" href="assets/css/bootstrap/bootstrap.min.css">
    <link rel="icon" href="assets/images/favicon.png">
    <style>
        .card {
            margin-top: 50vh;
            transform: translate(0px, -50%);
            min-width: 310px;
            text-align: center;
            box-shadow: 0px 7px 10px #33333324;
            border-radius: 22px;
            overflow: hidden;
        }

        .card h1 {
            font-family: sans-serif;
            font-size: 22px;
            font-weight: bold;
        }

        .card-header {
            background: darkblue;
            color: white;
            height: 49px;
        }

        body {
            background: #eeece9;
        }

        div#body-card h3 {
            font-size: 22px;
            padding: 25px 4px;
        }
    </style>



</head>

<body>

    <div class="container-fluid">
        <div class="d-flex justify-content-center">

            <div class="card">
                <div class="card-header">
                    <h1>Reportes <span id="titulo">Diarios</span></h1>
                </div>
                <div id="body-card" class="card-body d-flex justify-content-center">
                    <div class="spinner-grow text-primary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <div class="spinner-grow text-secondary" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <div class="spinner-grow text-success" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <!-- Latest compiled and minified JavaScript -->
    <script src="assets/js/jquery-3.5.1.min.js"></script>
    <script src="assets/js/bootstrap/bootstrap.min.js"></script>
    <?php
    if (!empty($_GET)) {
    ?>
        <script>
            var folder = "<?php echo $_GET['folder']; ?>";
            $("#titulo").text(folder);
        </script>
    <?php
    }
    ?>

    <script>
        var urls = [];
        var urlsMv = [];

        $(window).on('load', function() {

            var hoy = typeof folder !== 'undefined' ? folder : "";

            $.ajax({
                type: "POST",
                url: "controller/validate_files_controller.php",
                dataType: "json",
                data: {
                    "hoy" : hoy
                },
                async: false,
                success: function(response) {
                    console.log(response);
                    var message = "Error al validar los archivos <br>";
                    if (response.success) {
                        message = response.msg;
                        urls = response.urls;
                        urlsMv = response.urlsMv;
                    } else {
                        message += response.error;
                    }

                    $("#body-card").html("<h3>" + message + "</h3>")
                },
                error: function(error) {
                    $("#body-card").html("<h3>Error de conexion del servidor</h3>")

                    alert("Error de conexión del servidor");
                }
            });
        })

        function archivar(folderName) {
            var spinner = '<div class="spinner-grow text-primary" role="status"><span class="sr-only">Loading...</span></div><div class="spinner-grow text-secondary" role="status"><span class="sr-only">Loading...</span></div><div class="spinner-grow text-success" role="status"><span class="sr-only">Loading...</span> </div>'
            $("#body-card").html(spinner);
            $.ajax({
                type: "POST",
                url: "controller/mv_files_controller.php",
                dataType: "json",
                async: false,
                data: {
                    "urls": urlsMv,
                    "hoy": folderName
                },
                success: function (response) {
                    console.log(response);
                    var message = "Error al procesar los archivos <br>";
                    if(response.success){
                        message = response.msg;
                    }else{
                        message += response.error;
                    }

                    $("#body-card").html("<h3>"+message+"</h3>")
                },
                error: function (error) {
                    $("#body-card").html("<h3>Error de conexion del servidor</h3>")

                    alert("Error de conexión del servidor");
                }
            });
        }

        function exportar(folderName) {
            var spinner = '<div class="spinner-grow text-primary" role="status"><span class="sr-only">Loading...</span></div><div class="spinner-grow text-secondary" role="status"><span class="sr-only">Loading...</span></div><div class="spinner-grow text-success" role="status"><span class="sr-only">Loading...</span> </div>'
            $("#body-card").html(spinner);
            $.ajax({
                type: "POST",
                url: "controller/process_files_controller.php",
                dataType: "json",
                async: false,
                data: {
                    "urls": urls,
                    "urlsMv": urlsMv,
                    "hoy": folderName
                },
                success: function (response) {
                    console.log(response);
                    var message = "Error al procesar los archivos <br>";
                    if(response.success){
                        message = response.msg;
                    }else{
                        message += response.error;
                    }

                    $("#body-card").html("<h3>"+message+"</h3>")
                },
                error: function (error) {
                    $("#body-card").html("<h3>Error de conexion del servidor</h3>")

                    alert("Error de conexión del servidor");
                }
            });
        }
    </script>
</body>

</html>