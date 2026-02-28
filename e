/* --- OPTIMISATION FINALE 2 COLONNES MOBILE --- */
@media (max-width: 1024px) {
    .quantyss-expertise-grid {
        grid-template-columns: repeat(2, 1fr); /* 2 colonnes */
        gap: 10px; /* Espace minimaliste et élégant */
        padding: 5px;
    }

    .expertise-item {
        padding: 15px 10px; /* Padding serré pour maximiser l'espace texte */
        min-height: 160px; /* Assure une uniformité visuelle */
        display: flex;
        flex-direction: column;
        justify-content: center; /* Centre verticalement le contenu */
    }

    .icon-container {
        width: 38px;
        height: 38px;
        font-size: 16px;
        margin-bottom: 6px;
        color : #00AEEF;
    }

    /* Ajustement des textes pour éviter les coupures */
    .text-container h3 {
        font-size: 18px; 
        line-height: 1.2;
        margin-bottom: 6px;
        letter-spacing: -0.2px; /* Resserre un peu pour le mobile */
    }

    .text-container p {
        font-size: 13px; /* Taille "micro-copy" très élégante */
        line-height: 1.3;
        color: #777;
        /* Limite à 3 lignes pour garder l'alignement propre */
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }

    /* Le numéro fantôme en version mini */
    .expertise-item::before {
        font-size: 28px;
        top: 5px;
        right: 8px;
        opacity: 0.5;
    }
}