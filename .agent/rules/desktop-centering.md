---
description: Corriger le centrage du contenu sur desktop sans affecter le responsive
---

# Règle : Centrage Desktop

## Problème
En mode desktop, le contenu est décentré dans la zone principale (main).

## Solution
Ajouter un conteneur centré dans `.main-content` uniquement sur desktop (au-dessus du breakpoint lg).

## Implémentation CSS
Ajouter ce CSS spécifique pour desktop uniquement :

```scss
// Centrage desktop uniquement (au-dessus de 1024px)
@media (min-width: 1025px) {
    .main-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: var(--spacing-unit) calc(var(--spacing-unit) * 2);
    }
    
    // Centrer le header et footer sur desktop
    #main-header,
    #main-footer {
        max-width: 1400px;
        margin: 0 auto;
        padding: 0 calc(var(--spacing-unit) * 2);
    }
    
    #main-header {
        position: sticky;
    }
    
    #main-footer {
        position: sticky;
    }
}
```

## Contraintes
- Ne pas modifier les breakpoints existants
- Ne pas affecter le responsive mobile/tablette
- Appliquer uniquement sur desktop (min-width: 1025px)
