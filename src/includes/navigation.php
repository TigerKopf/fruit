    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container">
            <a class="navbar-brand" href="/"><?php echo APP_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'home') ? 'active' : ''; ?>" href="/">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'about') ? 'active' : ''; ?>" href="?page=about">Über Uns</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'products') ? 'active' : ''; ?>" href="?page=products">Produkte</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($page == 'contact') ? 'active' : ''; ?>" href="?page=contact">Kontakt</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>