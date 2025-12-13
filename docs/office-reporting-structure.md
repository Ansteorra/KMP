# Office Reporting Structure

This document shows the hierarchical reporting relationships between offices in the Kingdom Management Portal (KMP). Each department has its own diagram for clarity.

---

## 🏰 Nobility

```mermaid
flowchart TD
    Crown["👑 Crown"]
    Coronet["👑 Coronet"]
    PS["👑 Principality Sovereign"]
    LL["Local Landed"]
    
    LL --> Crown
```

---

## 📜 Seneschallate

```mermaid
flowchart TD
    KS["Kingdom Seneschal"]
    
    KSD["Kingdom Seneschal Deputy"]
    SMO["Kingdom Social Media Officer"]
    RS["Regional Seneschal"]
    
    KSD --> KS
    SMO --> KS
    RS --> KS
    
    SMD["Kingdom Social Media Deputy"]
    LSM["Local Social Media Officer"]
    RSD["Regional Seneschal Deputy"]
    LS["Local Seneschal"]
    
    SMD --> SMO
    LSM --> SMO
    RSD --> RS
    LS --> RS
    
    LSD["Local Seneschal Deputy"]
    LSD --> LS
```

---

## ⚔️ Marshallate

The Marshallate is the largest department. It's broken into sub-sections for readability.

### Earl Marshal Overview

```mermaid
flowchart TD
    KEM["🏛️ Kingdom Earl Marshal"]
    
    KEMD["Kingdom Earl Marshal Deputy"]
    KEMS["Kingdom Earl Marshal Deputy - Secretary"]
    PEM["Principality Earl Marshal"]
    
    KAM["Kingdom Armored Marshal"]
    KRM["Kingdom Rapier Marshal"]
    KEqM["Kingdom Equestrian Marshal"]
    KMM["Kingdom Missile Marshal"]
    
    KEMD --> KEM
    KEMS --> KEM
    PEM --> KEM
    
    KAM --> KEM
    KRM --> KEM
    KEqM --> KEM
    KMM --> KEM
```

### Armored Combat Branch

```mermaid
flowchart TD
    KAM["Kingdom Armored Marshal"]
    
    KAMD["Kingdom Armored Marshal Deputy"]
    AAM["At Large: Armored Authorizing Marshal"]
    KYAM["Kingdom Youth Armored Marshal"]
    RAM["Regional Armored Marshal"]
    
    KAMD --> KAM
    AAM --> KAM
    KYAM --> KAM
    RAM --> KAM
    
    AYAM["At Large: Youth Armored Authorizing Marshal"]
    LAM["Local Armored Marshal"]
    
    AYAM --> KYAM
    LAM --> RAM
    
    LAMD["Local Armored Marshal Deputy"]
    LAMD --> LAM
```

### Rapier Combat Branch

```mermaid
flowchart TD
    KRM["Kingdom Rapier Marshal"]
    
    KRMD["Kingdom Rapier Marshal Deputy"]
    ARAM["At Large: Rapier Authorizing Marshal"]
    ARSAM["At Large: Rapier Spear Authorizing Marshal"]
    ARRAE["At Large: Rapier Reduced Armor Experiment"]
    KCTM["Kingdom C&T Marshal"]
    KYRM["Kingdom Youth Rapier Marshal"]
    RRM["Regional Rapier Marshal"]
    
    KRMD --> KRM
    ARAM --> KRM
    ARSAM --> KRM
    ARRAE --> KRM
    KCTM --> KRM
    KYRM --> KRM
    RRM --> KRM
    
    ACTAM["At Large: C&T Authorizing Marshal"]
    ACT2H["At Large: C&T 2 Handed Weapons"]
    ACTHC["At Large: C&T Historic Combat Experiment"]
    
    ACTAM --> KCTM
    ACT2H --> KCTM
    ACTHC --> KCTM
    
    AYRM["At Large: Youth Rapier Authorizing Marshal"]
    RYRM["Regional Youth Rapier Marshal"]
    
    AYRM --> KYRM
    RYRM --> KYRM
    
    RRMD["Regional Rapier Marshal Deputy"]
    LRM["Local Rapier Marshal"]
    
    RRMD --> RRM
    LRM --> RRM
    
    LRMD["Local Rapier Marshal Deputy"]
    LRMD --> LRM
```

### Equestrian Branch

```mermaid
flowchart TD
    KEqM["Kingdom Equestrian Marshal"]
    
    AEqAM["At Large: Equestrian Authorizing Marshal"]
    AWLAM["At Large: Wooden Lance Authorizing Marshal"]
    
    AEqAM --> KEqM
    AWLAM --> KEqM
```

### Missile Combat Branch

```mermaid
flowchart TD
    KMM["Kingdom Missile Marshal"]
    
    KMMD["Kingdom Missile Marshal Deputy"]
    KTAM["Kingdom Target Archery Marshal"]
    KTWM["Kingdom Thrown Weapons Marshal"]
    KCAM["Kingdom Combat Archery Marshal"]
    KSWM["Kingdom Siege Weapons Marshal"]
    RTAM["Regional Target Archery Marshal"]
    
    KMMD --> KMM
    KTAM --> KMM
    KTWM --> KMM
    KCAM --> KMM
    KSWM --> KMM
    RTAM --> KMM
    
    ATAAM["At Large: Target Archery Authorizing Marshal"]
    LTAM["Local Target Archery Marshal"]
    
    ATAAM --> KTAM
    LTAM --> KTAM
    
    LTAMD["Local Target Archery Marshal Deputy"]
    LTAMD --> LTAM
    
    ATWAM["At Large: Thrown Weapons Authorizing Marshal"]
    LTWM["Local Thrown Weapons Marshal"]
    
    ATWAM --> KTWM
    LTWM --> KTWM
    
    ACAAM["At Large: Combat Archery Authorizing Marshal"]
    ACAAM --> KCAM
    
    ASWAM["At Large: Siege Weapons Authorizing Marshal"]
    ASWAM --> KSWM
```

---

## 🎨 Arts & Sciences

```mermaid
flowchart TD
    KMAS["Kingdom MoAS"]
    
    KMASD["Kingdom MoAS Deputy"]
    RMAS["Regional MoAS"]
    
    KMASD --> KMAS
    RMAS --> KMAS
    
    LMAS["Local MoAS"]
    LMAS --> RMAS
```

---

## 💻 Webministry

```mermaid
flowchart TD
    KW["Kingdom Webminister"]
    
    KWD["Kingdom Webminister Deputy"]
    KWAMP["Kingdom Webminister - AMP Admin"]
    RW["Regional Webminister"]
    LW["Local Webminister"]
    
    KWD --> KW
    KWAMP --> KW
    RW --> KW
    LW --> KW
```

---

## 💰 Treasury

```mermaid
flowchart TD
    KT["Kingdom Treasurer"]
    PC["Principality Consort"]
    
    KTD["Kingdom Treasurer Deputy"]
    RT["Regional Treasurer"]
    
    KTD --> KT
    RT --> KT
    
    LT["Local Treasurer"]
    LT --> RT
```

---

## 🏠 Chatelaine

```mermaid
flowchart TD
    KC["Kingdom Chatelaine"]
    
    KCD["Kingdom Chatelaine Deputy"]
    RC["Regional Chatelaine"]
    
    KCD --> KC
    RC --> KC
    
    LC["Local Chatelaine"]
    LC --> RC
    
    LCD["Local Chatelaine Deputy"]
    LCD --> LC
```

---

## 📰 Chronicler

```mermaid
flowchart TD
    KCH["Kingdom Chronicler"]
    
    KCHD["Kingdom Chronicler Deputy"]
    RCH["Regional Chronicler"]
    
    KCHD --> KCH
    RCH --> KCH
    
    RCHD["Regional Chronicler Deputy"]
    LCH["Local Chronicler"]
    
    RCHD --> RCH
    LCH --> RCH
```

---

## 🛡️ College of Heralds

```mermaid
flowchart TD
    SPH["Star Principal Herald"]
    
    KHD["Kingdom Herald Deputy"]
    RH["Regional Herald"]
    
    KHD --> SPH
    RH --> SPH
    
    RHD["Regional Herald Deputy"]
    LH["Local Herald"]
    LHD["Local Heraldry Deputy"]
    
    RHD --> RH
    LH --> RH
    LHD --> RH
```

---

## 👨‍👩‍👧‍👦 Youth and Family Office

```mermaid
flowchart TD
    KYFO["Kingdom Youth and Family Officer"]
    
    RYFO["Regional Youth and Family Officer"]
    RYFO --> KYFO
    
    LYFO["Local Youth and Family Officer"]
    LYFO --> RYFO
```

---

## Office Count Summary

| Department | Total Offices |
|------------|---------------|
| Marshallate | 47 |
| Seneschallate | 10 |
| Arts & Sciences | 4 |
| Webministry | 5 |
| Treasury | 5 |
| Chatelaine | 5 |
| Chronicler | 5 |
| College of Heralds | 6 |
| Youth and Family Office | 3 |
| Nobility | 4 |
| **Total** | **94** |

---

*Generated from KMP database on December 8, 2025*
