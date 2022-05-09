<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="">
    <meta name="author" content="Mark Otto, Jacob Thornton, and Bootstrap contributors">
    <meta name="generator" content="Hugo 0.84.0">
    <title>Gerar Base Fundiária - SIGEF</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet"
        integrity="sha384-1BmE4kWBq78iYhFldvKuhfTAU6auU8tT94WrHftjDbrCEXSU1oBoqyl2QvZ6jIW3" crossorigin="anonymous">
    <meta name="theme-color" content="#7952b3">
    <style>
        .bd-placeholder-img {
            font-size: 1.125rem;
            text-anchor: middle;
            -webkit-user-select: none;
            -moz-user-select: none;
            user-select: none;
        }

        @media (min-width: 768px) {
            .bd-placeholder-img-lg {
                font-size: 3.5rem;
            }
        }

    </style>
</head>

<body>

    <div class="col-lg-8 mx-auto p-3 py-md-5">
        <header class="d-flex align-items-center pb-3 mb-5 border-bottom">
            <a href="/" class="d-flex align-items-center text-dark text-decoration-none">
                <span class="fs-4">Gerar base fundária do SIGEF</span>
            </a>
        </header>

        <main>
            <div class="col-md-12 mb-3">
                Total de imóveis no banco de dados:
                <strong>{{ number_format($immobiles, 0, ',', '.') }}</strong><br />
                Total de vértices no banco de dados: <strong>{{ number_format($vertices, 0, ',', '.') }}</strong>
            </div>

            <form method="POST" action="{{ route('processFile') }}" enctype="multipart/form-data">
                @csrf
                <div class="form-group">
                    <label for="file">Arquivo HTML</label>
                    <input type="file" name="file" id="file" class="form-control" accept=".htm,.html" />
                </div>
                <div class="col-md-12 mt-3 mb-3">
                    <h4>OU</h4>
                </div>

                <div class="form-group">
                    <label for="link">Link do envio</label>
                    <input type="text" name="link" id="link" class="form-control" />
                </div>
                <div class="form-group mt-3">
                    <button class="btn btn-primary">
                        Enviar
                    </button>
                </div>
            </form>

            @if (session('total_download') || session('total_exists') || session('total_delete'))
                <div class="col-md-12 mt-3">
                    Total de download: <strong>{{ session('total_download') }}</strong><br />
                    Total existente: <strong>{{ session('total_exists') }}</strong><br />
                    Total para excluir: <strong>{{ session('total_delete') }}</strong><br />
                </div>
            @endif

            <div class="col-md-12 pt-3">
                <a href="/shape" class="btn btn-secondary">Gerar Shapefile</a>
            </div>
        </main>
        <footer class="pt-5 my-5 text-muted border-top">
            {{ date('Y') }}
        </footer>
    </div>

    <!-- JavaScript Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ka7Sk0Gln4gmtz2MlQnikT1wXgYsOg+OMhuP+IlRH9sENBO0LRn5q+8nbTov4+1p" crossorigin="anonymous">
    </script>
</body>

</html>
