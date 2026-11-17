/* Korrichess — espace web (issue #12). Application JavaScript légère, sans
   framework (ADR-005). Routage par hash (#/…), appels à /api/v1 avec le
   jeton Sanctum. Aucune donnée n'est rendue sans échappement HTML. */
'use strict';

(function () {
    var API = (typeof window.KORRICHESS_API === 'string' && window.KORRICHESS_API) || '/api/v1';
    var CLE_JETON = 'korrichess_jeton';

    /*
       Pièces d'échecs « Cburnett » © Colin M.L. Burnett (Wikimedia
       Commons), licence CC BY-SA 3.0 — attribution dans les mentions
       légales. Embarquées en data URI : aucune requête externe, aucun
       asset supplémentaire sur le mutualisé FTP.
    */
var PIECES = {
        P: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+Cjxzdmcgdmlld0JveD0iMCAwIDQ1IDQ1IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgd2lkdGg9IjQ1IiBoZWlnaHQ9IjQ1Ij4KICA8cGF0aCBkPSJtIDIyLjUsOSBjIC0yLjIxLDAgLTQsMS43OSAtNCw0IDAsMC44OSAwLjI5LDEuNzEgMC43OCwyLjM4IEMgMTcuMzMsMTYuNSAxNiwxOC41OSAxNiwyMSBjIDAsMi4wMyAwLjk0LDMuODQgMi40MSw1LjAzIEMgMTUuNDEsMjcuMDkgMTEsMzEuNTggMTEsMzkuNSBIIDM0IEMgMzQsMzEuNTggMjkuNTksMjcuMDkgMjYuNTksMjYuMDMgMjguMDYsMjQuODQgMjksMjMuMDMgMjksMjEgMjksMTguNTkgMjcuNjcsMTYuNSAyNS43MiwxNS4zOCAyNi4yMSwxNC43MSAyNi41LDEzLjg5IDI2LjUsMTMgYyAwLC0yLjIxIC0xLjc5LC00IC00LC00IHoiIHN0eWxlPSJvcGFjaXR5OjE7IGZpbGw6I2ZmZmZmZjsgZmlsbC1vcGFjaXR5OjE7IGZpbGwtcnVsZTpub256ZXJvOyBzdHJva2U6IzAwMDAwMDsgc3Ryb2tlLXdpZHRoOjEuNTsgc3Ryb2tlLWxpbmVjYXA6cm91bmQ7IHN0cm9rZS1saW5lam9pbjptaXRlcjsgc3Ryb2tlLW1pdGVybGltaXQ6NDsgc3Ryb2tlLWRhc2hhcnJheTpub25lOyBzdHJva2Utb3BhY2l0eToxOyIvPgo8L3N2Zz4K',
        N: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+DQo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPg0KPHN2ZyB2aWV3Qm94PSIwIDAgNDUgNDUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmVyc2lvbj0iMS4xIiB3aWR0aD0iNDUiIGhlaWdodD0iNDUiPg0KICA8ZyBzdHlsZT0ib3BhY2l0eToxOyBmaWxsOm5vbmU7IGZpbGwtb3BhY2l0eToxOyBmaWxsLXJ1bGU6ZXZlbm9kZDsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS13aWR0aDoxLjU7IHN0cm9rZS1saW5lY2FwOnJvdW5kO3N0cm9rZS1saW5lam9pbjpyb3VuZDtzdHJva2UtbWl0ZXJsaW1pdDo0OyBzdHJva2UtZGFzaGFycmF5Om5vbmU7IHN0cm9rZS1vcGFjaXR5OjE7IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwLDAuMykiPg0KICAgIDxwYXRoDQogICAgICBkPSJNIDIyLDEwIEMgMzIuNSwxMSAzOC41LDE4IDM4LDM5IEwgMTUsMzkgQyAxNSwzMCAyNSwzMi41IDIzLDE4Ig0KICAgICAgc3R5bGU9ImZpbGw6I2ZmZmZmZjsgc3Ryb2tlOiMwMDAwMDA7IiAvPg0KICAgIDxwYXRoDQogICAgICBkPSJNIDI0LDE4IEMgMjQuMzgsMjAuOTEgMTguNDUsMjUuMzcgMTYsMjcgQyAxMywyOSAxMy4xOCwzMS4zNCAxMSwzMSBDIDkuOTU4LDMwLjA2IDEyLjQxLDI3Ljk2IDExLDI4IEMgMTAsMjggMTEuMTksMjkuMjMgMTAsMzAgQyA5LDMwIDUuOTk3LDMxIDYsMjYgQyA2LDI0IDEyLDE0IDEyLDE0IEMgMTIsMTQgMTMuODksMTIuMSAxNCwxMC41IEMgMTMuMjcsOS41MDYgMTMuNSw4LjUgMTMuNSw3LjUgQyAxNC41LDYuNSAxNi41LDEwIDE2LjUsMTAgTCAxOC41LDEwIEMgMTguNSwxMCAxOS4yOCw4LjAwOCAyMSw3IEMgMjIsNyAyMiwxMCAyMiwxMCINCiAgICAgIHN0eWxlPSJmaWxsOiNmZmZmZmY7IHN0cm9rZTojMDAwMDAwOyIgLz4NCiAgICA8cGF0aA0KICAgICAgZD0iTSA5LjUgMjUuNSBBIDAuNSAwLjUgMCAxIDEgOC41LDI1LjUgQSAwLjUgMC41IDAgMSAxIDkuNSAyNS41IHoiDQogICAgICBzdHlsZT0iZmlsbDojMDAwMDAwOyBzdHJva2U6IzAwMDAwMDsiIC8+DQogICAgPHBhdGgNCiAgICAgIGQ9Ik0gMTUgMTUuNSBBIDAuNSAxLjUgMCAxIDEgIDE0LDE1LjUgQSAwLjUgMS41IDAgMSAxICAxNSAxNS41IHoiDQogICAgICB0cmFuc2Zvcm09Im1hdHJpeCgwLjg2NiwwLjUsLTAuNSwwLjg2Niw5LjY5MywtNS4xNzMpIg0KICAgICAgc3R5bGU9ImZpbGw6IzAwMDAwMDsgc3Ryb2tlOiMwMDAwMDA7IiAvPg0KICA8L2c+DQo8L3N2Zz4NCg==',
        B: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+DQo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPg0KPHN2ZyB2aWV3Qm94PSIwIDAgNDUgNDUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmVyc2lvbj0iMS4xIiB3aWR0aD0iNDUiIGhlaWdodD0iNDUiPg0KICA8ZyBzdHlsZT0ib3BhY2l0eToxOyBmaWxsOm5vbmU7IGZpbGwtcnVsZTpldmVub2RkOyBmaWxsLW9wYWNpdHk6MTsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS13aWR0aDoxLjU7IHN0cm9rZS1saW5lY2FwOnJvdW5kOyBzdHJva2UtbGluZWpvaW46cm91bmQ7IHN0cm9rZS1taXRlcmxpbWl0OjQ7IHN0cm9rZS1kYXNoYXJyYXk6bm9uZTsgc3Ryb2tlLW9wYWNpdHk6MTsiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDAsMC42KSI+DQogICAgPGcgc3R5bGU9ImZpbGw6I2ZmZmZmZjsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS1saW5lY2FwOmJ1dHQ7Ij4NCiAgICAgIDxwYXRoIGQ9Ik0gOSwzNiBDIDEyLjM5LDM1LjAzIDE5LjExLDM2LjQzIDIyLjUsMzQgQyAyNS44OSwzNi40MyAzMi42MSwzNS4wMyAzNiwzNiBDIDM2LDM2IDM3LjY1LDM2LjU0IDM5LDM4IEMgMzguMzIsMzguOTcgMzcuMzUsMzguOTkgMzYsMzguNSBDIDMyLjYxLDM3LjUzIDI1Ljg5LDM4Ljk2IDIyLjUsMzcuNSBDIDE5LjExLDM4Ljk2IDEyLjM5LDM3LjUzIDksMzguNSBDIDcuNjUsMzguOTkgNi42OCwzOC45NyA2LDM4IEMgNy4zNSwzNi41NCA5LDM2IDksMzYgeiIvPg0KICAgICAgPHBhdGggZD0iTSAxNSwzMiBDIDE3LjUsMzQuNSAyNy41LDM0LjUgMzAsMzIgQyAzMC41LDMwLjUgMzAsMzAgMzAsMzAgQyAzMCwyNy41IDI3LjUsMjYgMjcuNSwyNiBDIDMzLDI0LjUgMzMuNSwxNC41IDIyLjUsMTAuNSBDIDExLjUsMTQuNSAxMiwyNC41IDE3LjUsMjYgQyAxNy41LDI2IDE1LDI3LjUgMTUsMzAgQyAxNSwzMCAxNC41LDMwLjUgMTUsMzIgeiIvPg0KICAgICAgPHBhdGggZD0iTSAyNSA4IEEgMi41IDIuNSAwIDEgMSAgMjAsOCBBIDIuNSAyLjUgMCAxIDEgIDI1IDggeiIvPg0KICAgIDwvZz4NCiAgICA8cGF0aCBkPSJNIDE3LjUsMjYgTCAyNy41LDI2IE0gMTUsMzAgTCAzMCwzMCBNIDIyLjUsMTUuNSBMIDIyLjUsMjAuNSBNIDIwLDE4IEwgMjUsMTgiIHN0eWxlPSJmaWxsOm5vbmU7IHN0cm9rZTojMDAwMDAwOyBzdHJva2UtbGluZWpvaW46bWl0ZXI7Ii8+DQogIDwvZz4NCjwvc3ZnPg0K',
        R: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+DQo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPg0KPHN2ZyB2aWV3Qm94PSIwIDAgNDUgNDUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmVyc2lvbj0iMS4xIiB3aWR0aD0iNDUiIGhlaWdodD0iNDUiPg0KICA8ZyBzdHlsZT0ib3BhY2l0eToxOyBmaWxsOiNmZmZmZmY7IGZpbGwtb3BhY2l0eToxOyBmaWxsLXJ1bGU6ZXZlbm9kZDsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS13aWR0aDoxLjU7IHN0cm9rZS1saW5lY2FwOnJvdW5kO3N0cm9rZS1saW5lam9pbjpyb3VuZDtzdHJva2UtbWl0ZXJsaW1pdDo0OyBzdHJva2UtZGFzaGFycmF5Om5vbmU7IHN0cm9rZS1vcGFjaXR5OjE7IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwLDAuMykiPg0KICAgIDxwYXRoDQogICAgICBkPSJNIDksMzkgTCAzNiwzOSBMIDM2LDM2IEwgOSwzNiBMIDksMzkgeiAiDQogICAgICBzdHlsZT0ic3Ryb2tlLWxpbmVjYXA6YnV0dDsiIC8+DQogICAgPHBhdGgNCiAgICAgIGQ9Ik0gMTIsMzYgTCAxMiwzMiBMIDMzLDMyIEwgMzMsMzYgTCAxMiwzNiB6ICINCiAgICAgIHN0eWxlPSJzdHJva2UtbGluZWNhcDpidXR0OyIgLz4NCiAgICA8cGF0aA0KICAgICAgZD0iTSAxMSwxNCBMIDExLDkgTCAxNSw5IEwgMTUsMTEgTCAyMCwxMSBMIDIwLDkgTCAyNSw5IEwgMjUsMTEgTCAzMCwxMSBMIDMwLDkgTCAzNCw5IEwgMzQsMTQiDQogICAgICBzdHlsZT0ic3Ryb2tlLWxpbmVjYXA6YnV0dDsiIC8+DQogICAgPHBhdGgNCiAgICAgIGQ9Ik0gMzQsMTQgTCAzMSwxNyBMIDE0LDE3IEwgMTEsMTQiIC8+DQogICAgPHBhdGgNCiAgICAgIGQ9Ik0gMzEsMTcgTCAzMSwyOS41IEwgMTQsMjkuNSBMIDE0LDE3Ig0KICAgICAgc3R5bGU9InN0cm9rZS1saW5lY2FwOmJ1dHQ7IHN0cm9rZS1saW5lam9pbjptaXRlcjsiIC8+DQogICAgPHBhdGgNCiAgICAgIGQ9Ik0gMzEsMjkuNSBMIDMyLjUsMzIgTCAxMi41LDMyIEwgMTQsMjkuNSIgLz4NCiAgICA8cGF0aA0KICAgICAgZD0iTSAxMSwxNCBMIDM0LDE0Ig0KICAgICAgc3R5bGU9ImZpbGw6bm9uZTsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS1saW5lam9pbjptaXRlcjsiIC8+DQogIDwvZz4NCjwvc3ZnPg0K',
        Q: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+Cjxzdmcgdmlld0JveD0iMCAwIDQ1IDQ1IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgd2lkdGg9IjQ1IiBoZWlnaHQ9IjQ1Ij4KICA8ZyBzdHlsZT0iZmlsbDojZmZmZmZmO3N0cm9rZTojMDAwMDAwO3N0cm9rZS13aWR0aDoxLjU7c3Ryb2tlLWxpbmVqb2luOnJvdW5kIj4KICAgIDxwYXRoIGQ9Ik0gOSwyNiBDIDE3LjUsMjQuNSAzMCwyNC41IDM2LDI2IEwgMzguNSwxMy41IEwgMzEsMjUgTCAzMC43LDEwLjkgTCAyNS41LDI0LjUgTCAyMi41LDEwIEwgMTkuNSwyNC41IEwgMTQuMywxMC45IEwgMTQsMjUgTCA2LjUsMTMuNSBMIDksMjYgeiIvPgogICAgPHBhdGggZD0iTSA5LDI2IEMgOSwyOCAxMC41LDI4IDExLjUsMzAgQyAxMi41LDMxLjUgMTIuNSwzMSAxMiwzMy41IEMgMTAuNSwzNC41IDExLDM2IDExLDM2IEMgOS41LDM3LjUgMTEsMzguNSAxMSwzOC41IEMgMTcuNSwzOS41IDI3LjUsMzkuNSAzNCwzOC41IEMgMzQsMzguNSAzNS41LDM3LjUgMzQsMzYgQyAzNCwzNiAzNC41LDM0LjUgMzMsMzMuNSBDIDMyLjUsMzEgMzIuNSwzMS41IDMzLjUsMzAgQyAzNC41LDI4IDM2LDI4IDM2LDI2IEMgMjcuNSwyNC41IDE3LjUsMjQuNSA5LDI2IHoiLz4KICAgIDxwYXRoIGQ9Ik0gMTEuNSwzMCBDIDE1LDI5IDMwLDI5IDMzLjUsMzAiIHN0eWxlPSJmaWxsOm5vbmUiLz4KICAgIDxwYXRoIGQ9Ik0gMTIsMzMuNSBDIDE4LDMyLjUgMjcsMzIuNSAzMywzMy41IiBzdHlsZT0iZmlsbDpub25lIi8+CiAgICA8Y2lyY2xlIGN4PSI2IiBjeT0iMTIiIHI9IjIiIC8+CiAgICA8Y2lyY2xlIGN4PSIxNCIgY3k9IjkiIHI9IjIiIC8+CiAgICA8Y2lyY2xlIGN4PSIyMi41IiBjeT0iOCIgcj0iMiIgLz4KICAgIDxjaXJjbGUgY3g9IjMxIiBjeT0iOSIgcj0iMiIgLz4KICAgIDxjaXJjbGUgY3g9IjM5IiBjeT0iMTIiIHI9IjIiIC8+CiAgPC9nPgo8L3N2Zz4K',
        K: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+Cjxzdmcgdmlld0JveD0iMCAwIDQ1IDQ1IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0NSIgaGVpZ2h0PSI0NSI+CiAgPGcgZmlsbD0ibm9uZSIgZmlsbC1ydWxlPSJldmVub2RkIiBzdHJva2U9IiMwMDAiIHN0cm9rZS1saW5lY2FwPSJyb3VuZCIgc3Ryb2tlLWxpbmVqb2luPSJyb3VuZCIgc3Ryb2tlLXdpZHRoPSIxLjUiPgogICAgPHBhdGggc3Ryb2tlLWxpbmVqb2luPSJtaXRlciIgZD0iTTIyLjUgMTEuNjNWNk0yMCA4aDUiLz4KICAgIDxwYXRoIGZpbGw9IiNmZmYiIHN0cm9rZS1saW5lY2FwPSJidXR0IiBzdHJva2UtbGluZWpvaW49Im1pdGVyIiBkPSJNMjIuNSAyNXM0LjUtNy41IDMtMTAuNWMwIDAtMS0yLjUtMy0yLjVzLTMgMi41LTMgMi41Yy0xLjUgMyAzIDEwLjUgMyAxMC41Ii8+CiAgICA8cGF0aCBmaWxsPSIjZmZmIiBkPSJNMTIuNSAzN2M1LjUgMy41IDE0LjUgMy41IDIwIDB2LTdzOS00LjUgNi0xMC41Yy00LTYuNS0xMy41LTMuNS0xNiA0VjI3di0zLjVjLTIuNS03LjUtMTItMTAuNS0xNi00LTMgNiA2IDEwLjUgNiAxMC41djciLz4KICAgIDxwYXRoIGQ9Ik0xMi41IDMwYzUuNS0zIDE0LjUtMyAyMCAwbS0yMCAzLjVjNS41LTMgMTQuNS0zIDIwIDBtLTIwIDMuNWM1LjUtMyAxNC41LTMgMjAgMCIvPgogIDwvZz4KPC9zdmc+',
        p: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+Cjxzdmcgdmlld0JveD0iMCAwIDQ1IDQ1IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgd2lkdGg9IjQ1IiBoZWlnaHQ9IjQ1Ij4KICA8cGF0aCBkPSJtIDIyLjUsOSBjIC0yLjIxLDAgLTQsMS43OSAtNCw0IDAsMC44OSAwLjI5LDEuNzEgMC43OCwyLjM4IEMgMTcuMzMsMTYuNSAxNiwxOC41OSAxNiwyMSBjIDAsMi4wMyAwLjk0LDMuODQgMi40MSw1LjAzIEMgMTUuNDEsMjcuMDkgMTEsMzEuNTggMTEsMzkuNSBIIDM0IEMgMzQsMzEuNTggMjkuNTksMjcuMDkgMjYuNTksMjYuMDMgMjguMDYsMjQuODQgMjksMjMuMDMgMjksMjEgMjksMTguNTkgMjcuNjcsMTYuNSAyNS43MiwxNS4zOCAyNi4yMSwxNC43MSAyNi41LDEzLjg5IDI2LjUsMTMgYyAwLC0yLjIxIC0xLjc5LC00IC00LC00IHoiIHN0eWxlPSJvcGFjaXR5OjE7IGZpbGw6IzAwMDAwMDsgZmlsbC1vcGFjaXR5OjE7IGZpbGwtcnVsZTpub256ZXJvOyBzdHJva2U6IzAwMDAwMDsgc3Ryb2tlLXdpZHRoOjEuNTsgc3Ryb2tlLWxpbmVjYXA6cm91bmQ7IHN0cm9rZS1saW5lam9pbjptaXRlcjsgc3Ryb2tlLW1pdGVybGltaXQ6NDsgc3Ryb2tlLWRhc2hhcnJheTpub25lOyBzdHJva2Utb3BhY2l0eToxOyIvPgo8L3N2Zz4K',
        n: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+DQo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPg0KPHN2ZyB2aWV3Qm94PSIwIDAgNDUgNDUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmVyc2lvbj0iMS4xIiB3aWR0aD0iNDUiIGhlaWdodD0iNDUiPg0KICA8ZyBzdHlsZT0ib3BhY2l0eToxOyBmaWxsOm5vbmU7IGZpbGwtb3BhY2l0eToxOyBmaWxsLXJ1bGU6ZXZlbm9kZDsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS13aWR0aDoxLjU7IHN0cm9rZS1saW5lY2FwOnJvdW5kO3N0cm9rZS1saW5lam9pbjpyb3VuZDtzdHJva2UtbWl0ZXJsaW1pdDo0OyBzdHJva2UtZGFzaGFycmF5Om5vbmU7IHN0cm9rZS1vcGFjaXR5OjE7IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwLDAuMykiPg0KICAgIDxwYXRoDQogICAgICBkPSJNIDIyLDEwIEMgMzIuNSwxMSAzOC41LDE4IDM4LDM5IEwgMTUsMzkgQyAxNSwzMCAyNSwzMi41IDIzLDE4Ig0KICAgICAgc3R5bGU9ImZpbGw6IzAwMDAwMDsgc3Ryb2tlOiMwMDAwMDA7IiAvPg0KICAgIDxwYXRoDQogICAgICBkPSJNIDI0LDE4IEMgMjQuMzgsMjAuOTEgMTguNDUsMjUuMzcgMTYsMjcgQyAxMywyOSAxMy4xOCwzMS4zNCAxMSwzMSBDIDkuOTU4LDMwLjA2IDEyLjQxLDI3Ljk2IDExLDI4IEMgMTAsMjggMTEuMTksMjkuMjMgMTAsMzAgQyA5LDMwIDUuOTk3LDMxIDYsMjYgQyA2LDI0IDEyLDE0IDEyLDE0IEMgMTIsMTQgMTMuODksMTIuMSAxNCwxMC41IEMgMTMuMjcsOS41MDYgMTMuNSw4LjUgMTMuNSw3LjUgQyAxNC41LDYuNSAxNi41LDEwIDE2LjUsMTAgTCAxOC41LDEwIEMgMTguNSwxMCAxOS4yOCw4LjAwOCAyMSw3IEMgMjIsNyAyMiwxMCAyMiwxMCINCiAgICAgIHN0eWxlPSJmaWxsOiMwMDAwMDA7IHN0cm9rZTojMDAwMDAwOyIgLz4NCiAgICA8cGF0aA0KICAgICAgZD0iTSA5LjUgMjUuNSBBIDAuNSAwLjUgMCAxIDEgOC41LDI1LjUgQSAwLjUgMC41IDAgMSAxIDkuNSAyNS41IHoiDQogICAgICBzdHlsZT0iZmlsbDojZmZmZmZmOyBzdHJva2U6I2ZmZmZmZjsiIC8+DQogICAgPHBhdGgNCiAgICAgIGQ9Ik0gMTUgMTUuNSBBIDAuNSAxLjUgMCAxIDEgIDE0LDE1LjUgQSAwLjUgMS41IDAgMSAxICAxNSAxNS41IHoiDQogICAgICB0cmFuc2Zvcm09Im1hdHJpeCgwLjg2NiwwLjUsLTAuNSwwLjg2Niw5LjY5MywtNS4xNzMpIg0KICAgICAgc3R5bGU9ImZpbGw6I2ZmZmZmZjsgc3Ryb2tlOiNmZmZmZmY7IiAvPg0KICAgIDxwYXRoDQogICAgICBkPSJNIDI0LjU1LDEwLjQgTCAyNC4xLDExLjg1IEwgMjQuNiwxMiBDIDI3Ljc1LDEzIDMwLjI1LDE0LjQ5IDMyLjUsMTguNzUgQyAzNC43NSwyMy4wMSAzNS43NSwyOS4wNiAzNS4yNSwzOSBMIDM1LjIsMzkuNSBMIDM3LjQ1LDM5LjUgTCAzNy41LDM5IEMgMzgsMjguOTQgMzYuNjIsMjIuMTUgMzQuMjUsMTcuNjYgQyAzMS44OCwxMy4xNyAyOC40NiwxMS4wMiAyNS4wNiwxMC41IEwgMjQuNTUsMTAuNCB6ICINCiAgICAgIHN0eWxlPSJmaWxsOiNmZmZmZmY7IHN0cm9rZTpub25lOyIgLz4NCiAgPC9nPg0KPC9zdmc+DQo=',
        b: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+DQo8IURPQ1RZUEUgc3ZnIFBVQkxJQyAiLS8vVzNDLy9EVEQgU1ZHIDEuMS8vRU4iICJodHRwOi8vd3d3LnczLm9yZy9HcmFwaGljcy9TVkcvMS4xL0RURC9zdmcxMS5kdGQiPg0KPHN2ZyB2aWV3Qm94PSIwIDAgNDUgNDUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyIgdmVyc2lvbj0iMS4xIiB3aWR0aD0iNDUiIGhlaWdodD0iNDUiPg0KICA8ZyBzdHlsZT0ib3BhY2l0eToxOyBmaWxsOm5vbmU7IGZpbGwtcnVsZTpldmVub2RkOyBmaWxsLW9wYWNpdHk6MTsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS13aWR0aDoxLjU7IHN0cm9rZS1saW5lY2FwOnJvdW5kOyBzdHJva2UtbGluZWpvaW46cm91bmQ7IHN0cm9rZS1taXRlcmxpbWl0OjQ7IHN0cm9rZS1kYXNoYXJyYXk6bm9uZTsgc3Ryb2tlLW9wYWNpdHk6MTsiIHRyYW5zZm9ybT0idHJhbnNsYXRlKDAsMC42KSI+DQogICAgPGcgc3R5bGU9ImZpbGw6IzAwMDAwMDsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS1saW5lY2FwOmJ1dHQ7Ij4NCiAgICAgIDxwYXRoIGQ9Ik0gOSwzNiBDIDEyLjM5LDM1LjAzIDE5LjExLDM2LjQzIDIyLjUsMzQgQyAyNS44OSwzNi40MyAzMi42MSwzNS4wMyAzNiwzNiBDIDM2LDM2IDM3LjY1LDM2LjU0IDM5LDM4IEMgMzguMzIsMzguOTcgMzcuMzUsMzguOTkgMzYsMzguNSBDIDMyLjYxLDM3LjUzIDI1Ljg5LDM4Ljk2IDIyLjUsMzcuNSBDIDE5LjExLDM4Ljk2IDEyLjM5LDM3LjUzIDksMzguNSBDIDcuNjUsMzguOTkgNi42OCwzOC45NyA2LDM4IEMgNy4zNSwzNi41NCA5LDM2IDksMzYgeiIvPg0KICAgICAgPHBhdGggZD0iTSAxNSwzMiBDIDE3LjUsMzQuNSAyNy41LDM0LjUgMzAsMzIgQyAzMC41LDMwLjUgMzAsMzAgMzAsMzAgQyAzMCwyNy41IDI3LjUsMjYgMjcuNSwyNiBDIDMzLDI0LjUgMzMuNSwxNC41IDIyLjUsMTAuNSBDIDExLjUsMTQuNSAxMiwyNC41IDE3LjUsMjYgQyAxNy41LDI2IDE1LDI3LjUgMTUsMzAgQyAxNSwzMCAxNC41LDMwLjUgMTUsMzIgeiIvPg0KICAgICAgPHBhdGggZD0iTSAyNSA4IEEgMi41IDIuNSAwIDEgMSAgMjAsOCBBIDIuNSAyLjUgMCAxIDEgIDI1IDggeiIvPg0KICAgIDwvZz4NCiAgICA8cGF0aCBkPSJNIDE3LjUsMjYgTCAyNy41LDI2IE0gMTUsMzAgTCAzMCwzMCBNIDIyLjUsMTUuNSBMIDIyLjUsMjAuNSBNIDIwLDE4IEwgMjUsMTgiIHN0eWxlPSJmaWxsOm5vbmU7IHN0cm9rZTojZmZmZmZmOyBzdHJva2UtbGluZWpvaW46bWl0ZXI7Ii8+DQogIDwvZz4NCjwvc3ZnPg0K',
        r: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+Cjxzdmcgdmlld0JveD0iMCAwIDQ1IDQ1IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgd2lkdGg9IjQ1IiBoZWlnaHQ9IjQ1Ij4KICA8ZyBzdHlsZT0ib3BhY2l0eToxOyBmaWxsOiMwMDAwMDA7IGZpbGwtb3BhY2l0eToxOyBmaWxsLXJ1bGU6ZXZlbm9kZDsgc3Ryb2tlOiMwMDAwMDA7IHN0cm9rZS13aWR0aDoxLjU7IHN0cm9rZS1saW5lY2FwOnJvdW5kO3N0cm9rZS1saW5lam9pbjpyb3VuZDtzdHJva2UtbWl0ZXJsaW1pdDo0OyBzdHJva2UtZGFzaGFycmF5Om5vbmU7IHN0cm9rZS1vcGFjaXR5OjE7IiB0cmFuc2Zvcm09InRyYW5zbGF0ZSgwLDAuMykiPgogICAgPHBhdGgKICAgICAgZD0iTSA5LDM5IEwgMzYsMzkgTCAzNiwzNiBMIDksMzYgTCA5LDM5IHogIgogICAgICBzdHlsZT0ic3Ryb2tlLWxpbmVjYXA6YnV0dDsiIC8+CiAgICA8cGF0aAogICAgICBkPSJNIDEyLjUsMzIgTCAxNCwyOS41IEwgMzEsMjkuNSBMIDMyLjUsMzIgTCAxMi41LDMyIHogIgogICAgICBzdHlsZT0ic3Ryb2tlLWxpbmVjYXA6YnV0dDsiIC8+CiAgICA8cGF0aAogICAgICBkPSJNIDEyLDM2IEwgMTIsMzIgTCAzMywzMiBMIDMzLDM2IEwgMTIsMzYgeiAiCiAgICAgIHN0eWxlPSJzdHJva2UtbGluZWNhcDpidXR0OyIgLz4KICAgIDxwYXRoCiAgICAgIGQ9Ik0gMTQsMjkuNSBMIDE0LDE2LjUgTCAzMSwxNi41IEwgMzEsMjkuNSBMIDE0LDI5LjUgeiAiCiAgICAgIHN0eWxlPSJzdHJva2UtbGluZWNhcDpidXR0O3N0cm9rZS1saW5lam9pbjptaXRlcjsiIC8+CiAgICA8cGF0aAogICAgICBkPSJNIDE0LDE2LjUgTCAxMSwxNCBMIDM0LDE0IEwgMzEsMTYuNSBMIDE0LDE2LjUgeiAiCiAgICAgIHN0eWxlPSJzdHJva2UtbGluZWNhcDpidXR0OyIgLz4KICAgIDxwYXRoCiAgICAgIGQ9Ik0gMTEsMTQgTCAxMSw5IEwgMTUsOSBMIDE1LDExIEwgMjAsMTEgTCAyMCw5IEwgMjUsOSBMIDI1LDExIEwgMzAsMTEgTCAzMCw5IEwgMzQsOSBMIDM0LDE0IEwgMTEsMTQgeiAiCiAgICAgIHN0eWxlPSJzdHJva2UtbGluZWNhcDpidXR0OyIgLz4KICAgIDxwYXRoCiAgICAgIGQ9Ik0gMTIsMzUuNSBMIDMzLDM1LjUgTCAzMywzNS41IgogICAgICBzdHlsZT0iZmlsbDpub25lOyBzdHJva2U6I2ZmZmZmZjsgc3Ryb2tlLXdpZHRoOjE7IHN0cm9rZS1saW5lam9pbjptaXRlcjsiIC8+CiAgICA8cGF0aAogICAgICBkPSJNIDEzLDMxLjUgTCAzMiwzMS41IgogICAgICBzdHlsZT0iZmlsbDpub25lOyBzdHJva2U6I2ZmZmZmZjsgc3Ryb2tlLXdpZHRoOjE7IHN0cm9rZS1saW5lam9pbjptaXRlcjsiIC8+CiAgICA8cGF0aAogICAgICBkPSJNIDE0LDI5LjUgTCAzMSwyOS41IgogICAgICBzdHlsZT0iZmlsbDpub25lOyBzdHJva2U6I2ZmZmZmZjsgc3Ryb2tlLXdpZHRoOjE7IHN0cm9rZS1saW5lam9pbjptaXRlcjsiIC8+CiAgICA8cGF0aAogICAgICBkPSJNIDE0LDE2LjUgTCAzMSwxNi41IgogICAgICBzdHlsZT0iZmlsbDpub25lOyBzdHJva2U6I2ZmZmZmZjsgc3Ryb2tlLXdpZHRoOjE7IHN0cm9rZS1saW5lam9pbjptaXRlcjsiIC8+CiAgICA8cGF0aAogICAgICBkPSJNIDExLDE0IEwgMzQsMTQiCiAgICAgIHN0eWxlPSJmaWxsOm5vbmU7IHN0cm9rZTojZmZmZmZmOyBzdHJva2Utd2lkdGg6MTsgc3Ryb2tlLWxpbmVqb2luOm1pdGVyOyIgLz4KICA8L2c+Cjwvc3ZnPgo=',
        q: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0idXRmLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIKImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+Cjxzdmcgdmlld0JveD0iMCAwIDQ1IDQ1IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgd2lkdGg9IjQ1IgpoZWlnaHQ9IjQ1Ij4KICA8ZyBzdHlsZT0iZmlsbDojMDAwMDAwO3N0cm9rZTojMDAwMDAwO3N0cm9rZS13aWR0aDoxLjU7IHN0cm9rZS1saW5lY2FwOnJvdW5kO3N0cm9rZS1saW5lam9pbjpyb3VuZCI+CgogICAgPHBhdGggZD0iTSA5LDI2IEMgMTcuNSwyNC41IDMwLDI0LjUgMzYsMjYgTCAzOC41LDEzLjUgTCAzMSwyNSBMIDMwLjcsMTAuOSBMIDI1LjUsMjQuNSBMIDIyLjUsMTAgTCAxOS41LDI0LjUgTCAxNC4zLDEwLjkgTCAxNCwyNSBMIDYuNSwxMy41IEwgOSwyNiB6IgogICAgc3R5bGU9InN0cm9rZS1saW5lY2FwOmJ1dHQ7ZmlsbDojMDAwMDAwIiAvPgogICAgPHBhdGggZD0ibSA5LDI2IGMgMCwyIDEuNSwyIDIuNSw0IDEsMS41IDEsMSAwLjUsMy41IC0xLjUsMSAtMSwyLjUgLTEsMi41IC0xLjUsMS41IDAsMi41IDAsMi41IDYuNSwxIDE2LjUsMSAyMywwIDAsMCAxLjUsLTEgMCwtMi41IDAsMCAwLjUsLTEuNSAtMSwtMi41IC0wLjUsLTIuNSAtMC41LC0yIDAuNSwtMy41IDEsLTIgMi41LC0yIDIuNSwtNCAtOC41LC0xLjUgLTE4LjUsLTEuNSAtMjcsMCB6IiAvPgogICAgPHBhdGggZD0iTSAxMS41LDMwIEMgMTUsMjkgMzAsMjkgMzMuNSwzMCIgLz4KICAgIDxwYXRoIGQ9Im0gMTIsMzMuNSBjIDYsLTEgMTUsLTEgMjEsMCIgLz4KICAgIDxjaXJjbGUgY3g9IjYiIGN5PSIxMiIgcj0iMiIgLz4KICAgIDxjaXJjbGUgY3g9IjE0IiBjeT0iOSIgcj0iMiIgLz4KICAgIDxjaXJjbGUgY3g9IjIyLjUiIGN5PSI4IiByPSIyIiAvPgogICAgPGNpcmNsZSBjeD0iMzEiIGN5PSI5IiByPSIyIiAvPgogICAgPGNpcmNsZSBjeD0iMzkiIGN5PSIxMiIgcj0iMiIgLz4KICAgIDxwYXRoIGQ9Ik0gMTEsMzguNSBBIDM1LDM1IDEgMCAwIDM0LDM4LjUiCiAgICBzdHlsZT0iZmlsbDpub25lOyBzdHJva2U6IzAwMDAwMDtzdHJva2UtbGluZWNhcDpidXR0OyIgLz4KICAgIDxnIHN0eWxlPSJmaWxsOm5vbmU7IHN0cm9rZTojZmZmZmZmOyI+CiAgICAgIDxwYXRoIGQ9Ik0gMTEsMjkgQSAzNSwzNSAxIDAgMSAzNCwyOSIgLz4KICAgICAgPHBhdGggZD0iTSAxMi41LDMxLjUgTCAzMi41LDMxLjUiIC8+CiAgICAgIDxwYXRoIGQ9Ik0gMTEuNSwzNC41IEEgMzUsMzUgMSAwIDAgMzMuNSwzNC41IiAvPgogICAgICA8cGF0aCBkPSJNIDEwLjUsMzcuNSBBIDM1LDM1IDEgMCAwIDM0LjUsMzcuNSIgLz4KICAgIDwvZz4KICA8L2c+Cjwvc3ZnPgo=',
        k: 'data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiIHN0YW5kYWxvbmU9Im5vIj8+CjwhRE9DVFlQRSBzdmcgUFVCTElDICItLy9XM0MvL0RURCBTVkcgMS4xLy9FTiIgImh0dHA6Ly93d3cudzMub3JnL0dyYXBoaWNzL1NWRy8xLjEvRFREL3N2ZzExLmR0ZCI+Cjxzdmcgdmlld0JveD0iMCAwIDQ1IDQ1IiB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgd2lkdGg9IjQ1IiBoZWlnaHQ9IjQ1Ij4KICA8ZyBzdHlsZT0iZmlsbDpub25lOyBmaWxsLW9wYWNpdHk6MTsgZmlsbC1ydWxlOmV2ZW5vZGQ7IHN0cm9rZTojMDAwMDAwOyBzdHJva2Utd2lkdGg6MS41OyBzdHJva2UtbGluZWNhcDpyb3VuZDtzdHJva2UtbGluZWpvaW46cm91bmQ7c3Ryb2tlLW1pdGVybGltaXQ6NDsgc3Ryb2tlLWRhc2hhcnJheTpub25lOyBzdHJva2Utb3BhY2l0eToxOyI+CiAgICA8cGF0aCBkPSJNIDIyLjUsMTEuNjMgTCAyMi41LDYiIHN0eWxlPSJmaWxsOm5vbmU7IHN0cm9rZTojMDAwMDAwOyBzdHJva2UtbGluZWpvaW46bWl0ZXI7IiBpZD0icGF0aDY1NzAiLz4KICAgIDxwYXRoIGQ9Ik0gMjIuNSwyNSBDIDIyLjUsMjUgMjcsMTcuNSAyNS41LDE0LjUgQyAyNS41LDE0LjUgMjQuNSwxMiAyMi41LDEyIEMgMjAuNSwxMiAxOS41LDE0LjUgMTkuNSwxNC41IEMgMTgsMTcuNSAyMi41LDI1IDIyLjUsMjUiIHN0eWxlPSJmaWxsOiMwMDAwMDA7ZmlsbC1vcGFjaXR5OjE7IHN0cm9rZS1saW5lY2FwOmJ1dHQ7IHN0cm9rZS1saW5lam9pbjptaXRlcjsiLz4KICAgIDxwYXRoIGQ9Ik0gMTIuNSwzNyBDIDE4LDQwLjUgMjcsNDAuNSAzMi41LDM3IEwgMzIuNSwzMCBDIDMyLjUsMzAgNDEuNSwyNS41IDM4LjUsMTkuNSBDIDM0LjUsMTMgMjUsMTYgMjIuNSwyMy41IEwgMjIuNSwyNyBMIDIyLjUsMjMuNSBDIDIwLDE2IDEwLjUsMTMgNi41LDE5LjUgQyAzLjUsMjUuNSAxMi41LDMwIDEyLjUsMzAgTCAxMi41LDM3IiBzdHlsZT0iZmlsbDojMDAwMDAwOyBzdHJva2U6IzAwMDAwMDsiLz4KICAgIDxwYXRoIGQ9Ik0gMjAsOCBMIDI1LDgiIHN0eWxlPSJmaWxsOm5vbmU7IHN0cm9rZTojMDAwMDAwOyBzdHJva2UtbGluZWpvaW46bWl0ZXI7Ii8+CiAgICA8cGF0aCBkPSJNIDMyLDI5LjUgQyAzMiwyOS41IDQwLjUsMjUuNSAzOC4wMywxOS44NSBDIDM0LjE1LDE0IDI1LDE4IDIyLjUsMjQuNSBMIDIyLjUsMjYuNiBMIDIyLjUsMjQuNSBDIDIwLDE4IDEwLjg1LDE0IDYuOTcsMTkuODUgQyA0LjUsMjUuNSAxMywyOS41IDEzLDI5LjUiIHN0eWxlPSJmaWxsOm5vbmU7IHN0cm9rZTojZmZmZmZmOyIvPgogICAgPHBhdGggZD0iTSAxMi41LDMwIEMgMTgsMjcgMjcsMjcgMzIuNSwzMCBNIDEyLjUsMzMuNSBDIDE4LDMwLjUgMjcsMzAuNSAzMi41LDMzLjUgTSAxMi41LDM3IEMgMTgsMzQgMjcsMzQgMzIuNSwzNyIgc3R5bGU9ImZpbGw6bm9uZTsgc3Ryb2tlOiNmZmZmZmY7Ii8+CiAgPC9nPgo8L3N2Zz4K',
    };

    /** Rendu d'une pièce en <img> (SVG embarqués, style Cburnett). */
    function svgPiece(caractere) {
        var uri = PIECES[caractere];
        if (!uri) { return '?'; }
        return '<img class="piece" src="' + uri + '" alt="">';
    }


    // ------------------------------------------------ utilitaires

    function echapperHtml(valeur) {
        return String(valeur === null || valeur === undefined ? '' : valeur)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
    }

    function jeton() {
        try { return localStorage.getItem(CLE_JETON); } catch (e) { return null; }
    }
    function poserJeton(t) { try { localStorage.setItem(CLE_JETON, t); } catch (e) { /* stockage indisponible */ } }
    function purgerJeton() { try { localStorage.removeItem(CLE_JETON); } catch (e) { /* idem */ } }

    function api(chemin, options) {
        options = options || {};
        var entetes = { 'Accept': 'application/json' };
        if (options.body !== undefined) { entetes['Content-Type'] = 'application/json'; }
        if (jeton() !== null) { entetes['Authorization'] = 'Bearer ' + jeton(); }
        return fetch(API + chemin, {
            method: options.methode || 'GET',
            headers: entetes,
            body: options.body !== undefined ? JSON.stringify(options.body) : undefined
        }).then(function (reponse) {
            return reponse.json().catch(function () { return {}; }).then(function (corps) {
                if (reponse.status === 401 && chemin.indexOf('/auth/') !== 0) {
                    purgerJeton();
                    aller('#/connexion');
                }
                return { status: reponse.status, body: corps };
            });
        });
    }

    function aller(hash) { if (location.hash !== hash) { location.hash = hash; } else { router(); } }

    function courteDate(valeur) {
        if (!valeur) { return '—'; }
        var date = new Date(String(valeur).replace(/\./g, '-').replace('T', ' '));
        if (isNaN(date.getTime())) { return String(valeur).slice(0, 10); }
        return date.toLocaleDateString('fr-FR');
    }

    function libelleResultat(partie) {
        if (!partie.result) { return '—'; }
        if (partie.result.indexOf('1/2') === 0 || partie.result.indexOf('\u00BD') === 0) { return 'nulle'; }
        var blancGagne = partie.result === '1-0';
        if (partie.color === 'white') { return blancGagne ? 'victoire' : 'défaite'; }
        return blancGagne ? 'défaite' : 'victoire';
    }

    function telecharger(nom, texte) {
        var blob = new Blob([texte], { type: 'application/json' });
        var url = URL.createObjectURL(blob);
        var lien = document.createElement('a');
        lien.href = url;
        lien.download = nom;
        document.body.appendChild(lien);
        lien.click();
        lien.remove();
        URL.revokeObjectURL(url);
    }

    var vue = function () { return document.getElementById('vue'); };

    // ------------------------------------------------ connexion / inscription (W1)

    function vueConnexion() {
        document.body.classList.add('sans-session');
        vue().innerHTML =
            '<div class="authencarte">' +
            '  <h1>Bienvenue !</h1>' +
            '  <div class="authent-onglets" role="tablist">' +
            '    <button type="button" id="onglet-connexion" role="tab" aria-selected="true">Se connecter</button>' +
            '    <button type="button" id="onglet-inscription" role="tab" aria-selected="false">Créer un compte</button>' +
            '  </div>' +
            '  <form id="formulaire-connexion">' +
            '    <div class="champ"><label for="courriel">Adresse e-mail</label>' +
            '      <input id="courriel" name="email" type="email" autocomplete="email" required></div>' +
            '    <div class="champ"><label for="motdepasse">Mot de passe</label>' +
            '      <input id="motdepasse" name="password" type="password" autocomplete="current-password" required></div>' +
            '    <p class="message-erreur" id="erreur-connexion" role="alert"></p>' +
            '    <button class="bouton bouton-principal" type="submit">Se connecter</button>' +
            '  </form>' +
            '  <form id="formulaire-inscription" hidden>' +
            '    <div class="champ"><label for="courriel2">Adresse e-mail</label>' +
            '      <input id="courriel2" name="email" type="email" autocomplete="email" required></div>' +
            '    <div class="champ"><label for="motdepasse2">Mot de passe (8 caractères min.)</label>' +
            '      <input id="motdepasse2" name="password" type="password" autocomplete="new-password" minlength="8" required></div>' +
            '    <p class="message-erreur" id="erreur-inscription" role="alert"></p>' +
            '    <button class="bouton bouton-principal" type="submit">Créer mon compte</button>' +
            '  </form>' +
            '</div>';

        var formulaireConnexion = document.getElementById('formulaire-connexion');
        var formulaireInscription = document.getElementById('formulaire-inscription');

        document.getElementById('onglet-connexion').addEventListener('click', function () {
            formulaireConnexion.hidden = false;
            formulaireInscription.hidden = true;
            this.setAttribute('aria-selected', 'true');
            document.getElementById('onglet-inscription').setAttribute('aria-selected', 'false');
        });
        document.getElementById('onglet-inscription').addEventListener('click', function () {
            formulaireConnexion.hidden = true;
            formulaireInscription.hidden = false;
            this.setAttribute('aria-selected', 'true');
            document.getElementById('onglet-connexion').setAttribute('aria-selected', 'false');
        });

        formulaireConnexion.addEventListener('submit', function (evenement) {
            evenement.preventDefault();
            var erreur = document.getElementById('erreur-connexion');
            erreur.textContent = '';
            api('/auth/login', {
                methode: 'POST',
                body: {
                    email: document.getElementById('courriel').value,
                    password: document.getElementById('motdepasse').value
                }
            }).then(function (r) {
                if (r.status === 200 && r.body.token) {
                    poserJeton(r.body.token);
                    document.body.classList.remove('sans-session');
                    aller('#/tableau-de-bord');
                    return;
                }
                erreur.textContent = r.body.error === 'identifiants_invalides'
                    ? 'E-mail ou mot de passe incorrect.'
                    : 'Connexion impossible, réessaie dans un instant.';
            });
        });

        formulaireInscription.addEventListener('submit', function (evenement) {
            evenement.preventDefault();
            var erreur = document.getElementById('erreur-inscription');
            erreur.textContent = '';
            api('/auth/register', {
                methode: 'POST',
                body: {
                    email: document.getElementById('courriel2').value,
                    password: document.getElementById('motdepasse2').value
                }
            }).then(function (r) {
                if (r.status === 201 && r.body.token) {
                    poserJeton(r.body.token);
                    document.body.classList.remove('sans-session');
                    aller('#/tableau-de-bord');
                    return;
                }
                if (r.status === 422 && r.body.errors) {
                    if (r.body.errors.password) { erreur.textContent = 'Le mot de passe est trop court (8 caractères minimum).'; return; }
                    if (r.body.errors.email) { erreur.textContent = 'Cette adresse e-mail ne semble pas valide.'; return; }
                }
                if (r.body.error === 'email_deja_utilise') { erreur.textContent = 'Cette adresse a déjà un compte. Connecte-toi plutôt.'; return; }
                if (r.body.error === 'email_invalide') { erreur.textContent = 'Cette adresse e-mail ne semble pas valide.'; return; }
                erreur.textContent = 'Inscription impossible, réessaie dans un instant.';
            });
        });
    }

    // ------------------------------------------------ tableau de bord (W2)

    function vueTableauDeBord() {
        vue().innerHTML = '<p>Chargement…</p>';
        // Les compteurs viennent du serveur (/me/stats) : les calculer sur les
        // 200 dernières parties sous-estimait les totaux dès 200+ parties.
        Promise.all([
            api('/games?limit=8&sort=played_at&order=desc'),
            api('/me/stats')
        ]).then(function (reponses) {
            var r = reponses[0];
            if (r.status !== 200) { return; }
            var parties = r.body.games || [];
            var stats = reponses[1].status === 200 ? reponses[1].body : null;
            var total = stats ? (stats.total || 0) : (r.body.total || parties.length);
            var victoires = stats ? (stats.victoires || 0) : 0;
            var nulles = stats ? (stats.nulles || 0) : 0;
            var defaites = stats ? (stats.defaites || 0) : 0;
            var taux = stats ? (stats.taux_victoires || 0) : (total > 0 ? Math.round((victoires / total) * 100) : 0);
            var recentes = parties.slice(0, 8).map(function (p) {
                return '<tr>' +
                    '<td>' + echapperHtml(courteDate(p.played_at)) + '</td>' +
                    '<td><a href="#/rejeu/' + echapperHtml(p.id) + '">' + echapperHtml(p.opponent || 'adversaire inconnu') + '</a></td>' +
                    '<td>' + (p.color === 'white' ? 'blancs' : 'noirs') + '</td>' +
                    '<td>' + echapperHtml(libelleResultat(p)) + '</td>' +
                    '<td>' + echapperHtml(p.source || '') + '</td>' +
                    '</tr>';
            }).join('');

            vue().innerHTML =
                '<h1>Tableau de bord</h1>' +
                '<div class="stat-grille">' +
                '  <div class="stat-carte"><span class="valeur">' + total + '</span><span class="libelle">parties</span></div>' +
                '  <div class="stat-carte"><span class="valeur">' + victoires + '</span><span class="libelle">victoires</span></div>' +
                '  <div class="stat-carte"><span class="valeur">' + nulles + '</span><span class="libelle">nulles</span></div>' +
                '  <div class="stat-carte"><span class="valeur">' + defaites + '</span><span class="libelle">défaites</span></div>' +
                '  <div class="stat-carte"><span class="valeur">' + taux + ' %</span><span class="libelle">de victoires</span></div>' +
                '</div>' +
                '<p><a class="bouton bouton-principal" href="#/saisie">Saisir une partie papier</a></p>' +

                '<h2>Synchroniser mes parties</h2>' +
                '<form class="filtres" id="formulaire-sync">' +
                '  <div class="champ"><label for="sync-plateforme">Plateforme</label>' +
                '    <select id="sync-plateforme"><option value="lichess">Lichess</option><option value="chesscom">Chess.com</option></select></div>' +
                '  <div class="champ"><label for="sync-pseudo">Pseudo</label>' +
                '    <input id="sync-pseudo" type="text" placeholder="Ton pseudo" required></div>' +
                '  <div class="filtres-actions"><button class="bouton bouton-principal" type="submit">Synchroniser</button></div>' +
                '  <p class="message-ok" id="sync-retour" style="grid-column:1/-1"></p>' +
                '</form>' +

                '<h2>Parties récentes</h2>' +
                (parties.length === 0
                    ? '<p>Aucune partie pour l\'instant. Synchronise un pseudo ci-dessus pour commencer !</p>'
                    : '<div class="tableau-wrap"><table class="tableau">' +
                      '<thead><tr><th>Date</th><th>Adversaire</th><th>Couleur</th><th>Résultat</th><th>Source</th></tr></thead>' +
                      '<tbody>' + recentes + '</tbody></table></div>') +
                '<p class="prose" style="color:var(--encre-doux)">Les statistiques détaillées de progression arrivent plus tard (V3).</p>';

            var retour = document.getElementById('sync-retour');
            document.getElementById('formulaire-sync').addEventListener('submit', function (evenement) {
                evenement.preventDefault();
                retour.textContent = '';
                api('/sync', {
                    methode: 'POST',
                    body: {
                        platform: document.getElementById('sync-plateforme').value,
                        username: document.getElementById('sync-pseudo').value
                    }
                }).then(function (res) {
                    if (res.status === 202) {
                        retour.textContent = 'Synchronisation lancée en arrière-plan…';
                        suivreSync(
                            document.getElementById('sync-plateforme').value,
                            document.getElementById('sync-pseudo').value,
                            Date.now(),
                            0
                        );
                    } else if (res.body.error === 'plateforme_inconnue') {
                        retour.textContent = 'Plateforme inconnue.';
                    } else if (res.body.error === 'pseudo_invalide') {
                        retour.textContent = 'Ce pseudo ne semble pas valide.';
                    } else {
                        retour.textContent = 'Synchronisation impossible pour l\'instant.';
                    }
                });
            });

            // Suivi de la sync asynchrone : on interroge /sync/statut toutes les
            // 5 secondes jusqu'à ce que le curseur du compte avance, puis on
            // recharge le tableau de bord. Sans nouvelle donnée au bout de 2
            // minutes, on rend la main au joueur sans message d'erreur.
            function suivreSync(plateforme, pseudo, depart, essais) {
                if (essais >= 24) {
                    retour.textContent = 'Synchronisation toujours en cours, reviens dans un instant.';
                    vueTableauDeBord();
                    return;
                }
                api('/sync/statut').then(function (res) {
                    if (res.status !== 200) { return; }
                    var lignes = res.body.synchronisations || [];
                    var ligne = null;
                    for (var i = 0; i < lignes.length; i++) {
                        if (lignes[i].platform === plateforme && lignes[i].username === pseudo) {
                            ligne = lignes[i];
                            break;
                        }
                    }
                    var terminee = ligne && ligne.last_sync_at && Date.parse(ligne.last_sync_at) > depart - 60000;
                    if (terminee) {
                        retour.textContent = 'Synchronisation terminée ! Tes dernières parties sont là.';
                        vueTableauDeBord();
                    } else {
                        setTimeout(function () { suivreSync(plateforme, pseudo, depart, essais + 1); }, 5000);
                    }
                });
            }
        });
    }

    // ------------------------------------------------ bibliothèque (W3)

    var filtresEnMemoire = {};

    function vueBibliotheque(offset) {
        offset = offset || 0;
        vue().innerHTML = '<p>Chargement…</p>';

        function charger() {
            var requete = '/games?limit=20&offset=' + offset + '&sort=played_at&order=desc';
            var courants = filtresEnMemoire;
            Object.keys(courants).forEach(function (cle) {
                if (courants[cle] !== '' && courants[cle] !== null && courants[cle] !== undefined) {
                    requete += '&' + encodeURIComponent(cle) + '=' + encodeURIComponent(courants[cle]);
                }
            });
            api(requete).then(function (r) {
                if (r.status !== 200) { return; }
                var total = r.body.total || 0;
                var lignes = (r.body.games || []).map(function (p) {
                    return '<tr>' +
                        '<td>' + echapperHtml(courteDate(p.played_at)) + '</td>' +
                        '<td><a href="#/rejeu/' + echapperHtml(p.id) + '">' + echapperHtml(p.opponent || '?') + '</a></td>' +
                        '<td>' + (p.color === 'white' ? 'blancs' : 'noirs') + '</td>' +
                        '<td>' + echapperHtml(libelleResultat(p)) + '</td>' +
                        '<td>' + echapperHtml(p.time_control || '—') + '</td>' +
                        '<td>' + echapperHtml(p.opening || '—') + '</td>' +
                        '<td>' + echapperHtml(p.source || '—') + (p.favorite ? ' ★' : '') +
                            (p.source === 'manual' ? ' <a href="#/saisie/' + echapperHtml(p.id) + '" title="Corriger la saisie">✎ corriger</a>' : '') + '</td>' +
                        '</tr>';
                }).join('');

                var pagePrecedente = offset >= 20 ? '<a class="bouton bouton-secondaire" href="#/bibliotheque/' + (offset - 20) + '">Précédentes</a>' : '';
                var pageSuivante = offset + 20 < total ? '<a class="bouton bouton-secondaire" href="#/bibliotheque/' + (offset + 20) + '">Suivantes</a>' : '';

                vue().innerHTML =
                    '<h1>Bibliothèque</h1>' +
                    '<p><a class="bouton bouton-principal" href="#/saisie">Saisir une partie papier</a></p>' +
                    '<form class="filtres" id="formulaire-filtres">' +
                    '  <div class="champ"><label for="f-q">Recherche</label>' +
                    '    <input id="f-q" type="text" placeholder="Adversaire, tournoi, ouverture…" value="' + echapperHtml(filtresEnMemoire.q || '') + '"></div>' +
                    '  <div class="champ"><label for="f-source">Source</label>' +
                    '    <select id="f-source"><option value="">Toutes</option><option value="lichess"' + (filtresEnMemoire.source === 'lichess' ? ' selected' : '') + '>Lichess</option><option value="chesscom"' + (filtresEnMemoire.source === 'chesscom' ? ' selected' : '') + '>Chess.com</option></select></div>' +
                    '  <div class="champ"><label for="f-couleur">Couleur</label>' +
                    '    <select id="f-couleur"><option value="">Toutes</option><option value="white"' + (filtresEnMemoire.color === 'white' ? ' selected' : '') + '>Blancs</option><option value="black"' + (filtresEnMemoire.color === 'black' ? ' selected' : '') + '>Noirs</option></select></div>' +
                    '  <div class="champ"><label for="f-resultat">Résultat</label>' +
                    '    <select id="f-resultat"><option value="">Tous</option><option value="1-0"' + (filtresEnMemoire.result === '1-0' ? ' selected' : '') + '>1-0</option><option value="0-1"' + (filtresEnMemoire.result === '0-1' ? ' selected' : '') + '>0-1</option><option value="1/2-1/2"' + (filtresEnMemoire.result === '1/2-1/2' ? ' selected' : '') + '>Nulle</option></select></div>' +
                    '  <div class="champ carree"><label for="f-favori">Favoris seulement</label>' +
                    '    <input id="f-favori" type="checkbox" ' + (filtresEnMemoire.favorite ? 'checked' : '') + '></div>' +
                    '  <div class="filtres-actions">' +
                    '    <button class="bouton bouton-principal" type="submit">Filtrer</button>' +
                    '    <button class="bouton bouton-secondaire" type="button" id="f-reset">Effacer</button>' +
                    '  </div>' +
                    '</form>' +
                    (total === 0
                        ? '<p>Aucune partie ne correspond. Synchronise tes pseudos depuis le tableau de bord.</p>'
                        : '<div class="tableau-wrap"><table class="tableau">' +
                          '<thead><tr><th>Date</th><th>Adversaire</th><th>Couleur</th><th>Résultat</th><th>Cadence</th><th>Ouverture</th><th>Source</th></tr></thead>' +
                          '<tbody>' + lignes + '</tbody></table></div>') +
                    '<p>' + total + ' partie(s) — page ' + (Math.floor(offset / 20) + 1) + '</p>' +
                    '<div style="display:flex;gap:0.6rem">' + pagePrecedente + pageSuivante + '</div>';

                document.getElementById('formulaire-filtres').addEventListener('submit', function (evenement) {
                    evenement.preventDefault();
                    filtresEnMemoire = {
                        q: document.getElementById('f-q').value,
                        source: document.getElementById('f-source').value,
                        color: document.getElementById('f-couleur').value,
                        result: document.getElementById('f-resultat').value,
                        favorite: document.getElementById('f-favori').checked ? '1' : ''
                    };
                    aller('#/bibliotheque/0');
                });
                document.getElementById('f-reset').addEventListener('click', function () {
                    filtresEnMemoire = {};
                    aller('#/bibliotheque/0');
                });
            });
        }
        charger();
    }

    // ------------------------------------------------ rejeu (W4)

    var ecouteurClavier = null;

    function damierDepuisFen(fen, inverser) {
        var placement = fen.split(' ')[0];
        var cases = [];
        var ligne = 0, colonne = 0;
        for (var i = 0; i < placement.length; i++) {
            var c = placement[i];
            if (c === '/') { colonne = 0; ligne++; continue; }
            if (c >= '1' && c <= '8') {
                for (var j = 0; j < Number(c); j++) {
                    cases.push({ piece: null, clair: (ligne + colonne) % 2 === 0 });
                    colonne++;
                }
                continue;
            }
            cases.push({ piece: c, clair: (ligne + colonne) % 2 === 0 });
            colonne++;
        }
        if (inverser) { cases.reverse(); }
        return cases.map(function (cellule) {
            var piece = cellule.piece ? svgPiece(cellule.piece) : '';
            return '<div class="case ' + (cellule.clair ? 'clair' : 'fonce') + '" aria-hidden="true">' + piece + '</div>';
        }).join('');
    }

    function vueRejeu(id) {
        vue().innerHTML = '<p>Chargement de la partie…</p>';
        Promise.all([
            api('/games/' + id),
            api('/games/' + id + '/replay')
        ]).then(function (reponses) {
            var detail = reponses[0], rejeu = reponses[1];
            if (detail.status !== 200) {
                vue().innerHTML = '<p>Partie introuvable. <a href="#/bibliotheque">Retour à la bibliothèque</a>.</p>';
                return;
            }
            var positions = (rejeu.status === 200 && rejeu.body.positions) ? rejeu.body.positions : null;
            var sans = (rejeu.status === 200 && rejeu.body.moves_san) ? rejeu.body.moves_san : [];
            var inverser = detail.body.color === 'black';
            var courant = 0;

            var listeCoups = '';
            for (var i = 0; i < sans.length; i += 2) {
                var numero = (i / 2) + 1;
                listeCoups += '<li class="numero">' + numero + '.</li>' +
                    '<li><button type="button" data-ply="' + i + '" class="coup">' + echapperHtml(sans[i]) + '</button></li>' +
                    (sans[i + 1] !== undefined
                        ? '<li><button type="button" data-ply="' + (i + 1) + '" class="coup">' + echapperHtml(sans[i + 1]) + '</button></li>'
                        : '<li></li>');
            }

            vue().innerHTML =
                '<p><a href="#/bibliotheque">← Bibliothèque</a></p>' +
                '<h1>' + echapperHtml(detail.body.opponent || 'Partie') +
                (detail.body.color === 'white' ? ' — avec les blancs' : ' — avec les noirs') + '</h1>' +
                '<p>' + echapperHtml(courteDate(detail.body.played_at)) + ' · ' +
                    echapperHtml(libelleResultat(detail.body)) + (detail.body.opening ? ' · ' + echapperHtml(detail.body.opening) : '') + '</p>' +
                (positions
                    ? '<div class="rejeu-disposition">' +
                      '  <div>' +
                      '    <div class="echiquier" id="echiquier" role="img" aria-label="Échiquier"></div>' +
                      '    <div class="rejeu-controles">' +
                      '      <button type="button" id="cp-debut" aria-label="Début">⏮</button>' +
                      '      <button type="button" id="cp-avant" aria-label="Coup précédent">◀</button>' +
                      '      <button type="button" id="cp-apres" aria-label="Coup suivant">▶</button>' +
                      '      <button type="button" id="cp-fin" aria-label="Fin">⏭</button>' +
                      '    </div>' +
                      '    <p class="rejeu-position" id="rejeu-position"></p>' +
                      '  </div>' +
                      '  <div class="coups" id="liste-coups"><ol>' + listeCoups + '</ol></div>' +
                      '</div>'
                    : '<p>Le rejeu n\'est pas disponible pour cette partie. PGN :</p>' +
                      '<pre style="white-space:pre-wrap">' + echapperHtml(detail.body.pgn || '') + '</pre>');

            if (!positions) { return; }

            function afficher(ply) {
                courant = Math.max(0, Math.min(ply, positions.length - 1));
                document.getElementById('echiquier').innerHTML = damierDepuisFen(positions[courant], inverser);
                document.getElementById('rejeu-position').textContent = courant === 0
                    ? 'Position de départ'
                    : 'Coup ' + Math.ceil(courant / 2) + (courant % 2 === 1 ? '… ' : '. ') + (sans[courant - 1] || '');
                var boutons = document.querySelectorAll('#liste-coups button.coup');
                for (var b = 0; b < boutons.length; b++) {
                    boutons[b].classList.toggle('courant', Number(boutons[b].getAttribute('data-ply')) === courant - 1);
                }
            }

            document.getElementById('cp-debut').addEventListener('click', function () { afficher(0); });
            document.getElementById('cp-avant').addEventListener('click', function () { afficher(courant - 1); });
            document.getElementById('cp-apres').addEventListener('click', function () { afficher(courant + 1); });
            document.getElementById('cp-fin').addEventListener('click', function () { afficher(positions.length - 1); });
            Array.prototype.forEach.call(document.querySelectorAll('#liste-coups button.coup'), function (bouton) {
                bouton.addEventListener('click', function () { afficher(Number(bouton.getAttribute('data-ply')) + 1); });
            });

            if (ecouteurClavier) { document.removeEventListener('keydown', ecouteurClavier); }
            ecouteurClavier = function (evenement) {
                if (evenement.key === 'ArrowLeft') { afficher(courant - 1); }
                if (evenement.key === 'ArrowRight') { afficher(courant + 1); }
            };
            document.addEventListener('keydown', ecouteurClavier);
            afficher(0);
        });
    }

    // ------------------------------------------------ saisie manuelle (S5 + S7, issue #25)

    var saisie = null;

    /** Décodage d'un FEN en carte { case -> pièce } (ex. 'e4' -> 'P'). */
    function fenVersCarte(fen) {
        var placement = fen.split(' ')[0];
        var colonnes = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        var rangees = [8, 7, 6, 5, 4, 3, 2, 1];
        var carte = {};
        var i = 0;
        for (var l = 0; l < rangees.length; l++) {
            for (var c = 0; c < 8; c++) {
                var car = placement[i];
                i++;
                if (car >= '1' && car <= '8') {
                    c += Number(car) - 1;
                    continue;
                }
                carte[colonnes[c] + rangees[l]] = car;
            }
        }
        return carte;
    }

    /** Noms des 64 cases dans l'ordre d'affichage (inversé pour les noirs). */
    function nomCases(inverser) {
        var colonnes = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h'];
        var rangees = ['8', '7', '6', '5', '4', '3', '2', '1'];
        if (inverser) { colonnes.reverse(); rangees.reverse(); }
        var noms = [];
        rangees.forEach(function (r) { colonnes.forEach(function (c) { noms.push(c + r); }); });
        return noms;
    }

    /** Damier de saisie : cases cliquables, sélection et destinations mises en évidence. */
    function damierSaisie(fen, inverser) {
        var carte = fenVersCarte(fen);
        return nomCases(inverser).map(function (nom) {
            var colonne = nom.charCodeAt(0) - 97;
            var rangee = Number(nom[1]);
            var clair = (colonne + rangee) % 2 === 0;
            var piece = carte[nom] ? svgPiece(carte[nom]) : '';
            var classes = ['case', clair ? 'clair' : 'fonce'];
            if (saisie.selection === nom) {
                classes.push('selectionnee');
            } else if (saisie.destinations.indexOf(nom) >= 0) {
                classes.push('destination');
            }
            return '<div class="' + classes.join(' ') + '" data-case="' + nom + '">' + piece + '</div>';
        }).join('');
    }

    function vueSaisie(id) {
        saisie = {
            id: id || null,
            moves: [],
            fen: null,
            san: [],
            legal: [],
            destinations: [],
            invalidPly: null,
            invalidMove: null,
            verification: true,
            couleur: 'white',
            selection: null,
            fiche: {}
        };

        vue().innerHTML = '<p>Chargement…</p>';
        var chargement = id
            ? api('/games/' + id).then(function (r) {
                if (r.status !== 200) { return r; }
                var d = r.body;
                saisie.moves = (d.raw_moves && d.raw_moves.length > 0) ? d.raw_moves : (d.moves_uci || []);
                saisie.couleur = d.color === 'black' ? 'black' : 'white';
                saisie.fiche = {
                    played_at: String(d.played_at || '').slice(0, 10),
                    opponent: d.opponent || '',
                    opponent_rating: d.opponent_rating,
                    result: d.result === '*' ? '' : (d.result || ''),
                    time_control: d.time_control || '',
                    tournament: d.tournament || '',
                    round: d.round || ''
                };
                return r;
            })
            : Promise.resolve({ status: 200 });

        chargement.then(function (r) {
            if (r.status !== 200) {
                vue().innerHTML = '<p>Partie introuvable. <a href="#/bibliotheque">Retour à la bibliothèque</a>.</p>';
                return;
            }
            construireVueSaisie();
            apresCoupSaisie(true);
        });
    }

    function construireVueSaisie() {
        var f = saisie.fiche;
        var dateDefaut = f.played_at || new Date().toISOString().slice(0, 10);
        vue().innerHTML =
            '<p><a href="#/bibliotheque">← Bibliothèque</a></p>' +
            '<h1>' + (saisie.id ? 'Corriger une saisie' : 'Saisir une partie') + '</h1>' +
            '<p class="prose" style="color:var(--encre-doux)">Transcris ta feuille de partie : clique tes coups sur l\'échiquier, ' +
            'ou tape-les en notation algébrique (ex. <strong>Nf3</strong>). Clique un coup de la liste pour tronquer à partir de là.</p>' +
            '<div class="saisie-options">' +
            '  <label class="carree"><input type="checkbox" id="saisie-verification" checked> Vérifier les coups (refuse un coup illégal)</label>' +
            '  <p style="color:var(--encre-doux);margin:0.3rem 0 0">Décoche si ta feuille contient une erreur : la partie sera gardée telle quelle et marquée « à vérifier ».</p>' +
            '</div>' +
            '<div class="rejeu-disposition">' +
            '  <div>' +
            '    <div class="echiquier saisie-echiquier" id="saisie-echiquier" aria-label="Échiquier de saisie"></div>' +
            '    <p class="rejeu-position" id="saisie-statut"></p>' +
            '    <form id="saisie-form-san" style="display:flex;gap:0.5rem;flex-wrap:wrap">' +
            '      <div class="champ" style="flex:1;min-width:9rem;margin:0"><label for="saisie-san-texte">Tape un coup (ex. Nf3)</label>' +
            '        <input id="saisie-san-texte" type="text" autocomplete="off"></div>' +
            '      <button class="bouton bouton-secondaire" type="submit" style="align-self:flex-end">Ajouter</button>' +
            '    </form>' +
            '    <div id="saisie-promotion" hidden style="display:flex;gap:0.5rem;justify-content:center;margin-top:0.6rem"></div>' +
            '    <p style="margin-top:0.8rem"><button type="button" class="bouton bouton-secondaire" id="saisie-retirer">↩ Retirer le dernier coup</button></p>' +
            '  </div>' +
            '  <div class="coups" id="saisie-liste"></div>' +
            '</div>' +

            '<h2>La fiche de la partie</h2>' +
            '<form class="filtres" id="saisie-fiche">' +
            '  <div class="champ"><label for="s-date">Date</label>' +
            '    <input id="s-date" type="date" value="' + echapperHtml(dateDefaut) + '" required></div>' +
            '  <div class="champ"><label for="s-adversaire">Adversaire</label>' +
            '    <input id="s-adversaire" type="text" value="' + echapperHtml(f.opponent || '') + '" required maxlength="64"></div>' +
            '  <div class="champ"><label for="s-classement">Classement adverse (facultatif)</label>' +
            '    <input id="s-classement" type="number" min="0" max="5000" value="' + echapperHtml(f.opponent_rating || '') + '"></div>' +
            '  <div class="champ"><label for="s-couleur">Tu jouais…</label>' +
            '    <select id="s-couleur"><option value="white"' + (saisie.couleur === 'white' ? ' selected' : '') + '>les blancs</option>' +
            '      <option value="black"' + (saisie.couleur === 'black' ? ' selected' : '') + '>les noirs</option></select></div>' +
            '  <div class="champ"><label for="s-resultat">Résultat</label>' +
            '    <select id="s-resultat">' +
            '      <option value=""' + (f.result === '' ? ' selected' : '') + '>je ne sais pas</option>' +
            '      <option value="1-0"' + (f.result === '1-0' ? ' selected' : '') + '>1–0</option>' +
            '      <option value="0-1"' + (f.result === '0-1' ? ' selected' : '') + '>0–1</option>' +
            '      <option value="1/2-1/2"' + (f.result === '1/2-1/2' ? ' selected' : '') + '>nulle</option>' +
            '    </select></div>' +
            '  <div class="champ"><label for="s-cadence">Cadence (ex. 15+10)</label>' +
            '    <input id="s-cadence" type="text" maxlength="24" value="' + echapperHtml(f.time_control || '') + '"></div>' +
            '  <div class="champ"><label for="s-tournoi">Tournoi</label>' +
            '    <input id="s-tournoi" type="text" maxlength="160" value="' + echapperHtml(f.tournament || '') + '"></div>' +
            '  <div class="champ"><label for="s-ronde">Ronde</label>' +
            '    <input id="s-ronde" type="text" maxlength="24" value="' + echapperHtml(f.round || '') + '"></div>' +
            '  <div class="filtres-actions">' +
            '    <button class="bouton bouton-principal" type="submit">' + (saisie.id ? 'Enregistrer la correction' : 'Enregistrer la partie') + '</button>' +
            '  </div>' +
            '  <p class="message-erreur" id="saisie-erreur" role="alert" style="grid-column:1/-1"></p>' +
            '</form>';

        document.getElementById('saisie-verification').addEventListener('change', function () {
            saisie.verification = this.checked;
        });
        document.getElementById('s-couleur').addEventListener('change', function () {
            saisie.couleur = this.value;
            saisie.selection = null;
            saisie.destinations = [];
            rafraichirSaisie();
        });
        document.getElementById('saisie-echiquier').addEventListener('click', function (evenement) {
            var cible = evenement.target.closest ? evenement.target.closest('.case') : null;
            if (cible) { clicCaseSaisie(cible.getAttribute('data-case')); }
        });
        document.getElementById('saisie-form-san').addEventListener('submit', function (evenement) {
            evenement.preventDefault();
            var champ = document.getElementById('saisie-san-texte');
            var valeur = champ.value.trim();
            if (valeur === '') { return; }
            champ.value = '';
            saisie.moves.push(valeur);
            apresCoupSaisie(false);
        });
        document.getElementById('saisie-retirer').addEventListener('click', function () {
            if (saisie.moves.length === 0) { return; }
            saisie.moves.pop();
            apresCoupSaisie(true);
        });
        document.getElementById('saisie-liste').addEventListener('click', function (evenement) {
            var bouton = evenement.target.closest ? evenement.target.closest('button.coup') : null;
            if (!bouton) { return; }
            saisie.moves = saisie.moves.slice(0, Number(bouton.getAttribute('data-tronque')));
            apresCoupSaisie(true);
        });
        document.getElementById('saisie-fiche').addEventListener('submit', envoyerSaisie);
    }

    /** Demande au serveur de rejouer la liste des coups et met l'interface à jour. */
    function apresCoupSaisie(recharge) {
        return api('/games/manual/preview', { methode: 'POST', body: { moves: saisie.moves } }).then(function (r) {
            if (r.status !== 200) {
                var statut = document.getElementById('saisie-statut');
                if (statut) { statut.textContent = 'Prévisualisation impossible, réessaie dans un instant.'; }
                return;
            }
            var b = r.body;
            var nouveauFautif = b.invalid_ply !== null && !recharge && b.invalid_ply === saisie.moves.length - 1;
            if (nouveauFautif && saisie.verification) {
                saisie.moves.pop();
                return apresCoupSaisie(true).then(function () {
                    document.getElementById('saisie-statut').textContent =
                        'Ce coup n\'est pas légal ici — vérifie ta feuille, ou décoche la vérification pour l\'enregistrer tel quel.';
                });
            }
            saisie.fen = b.fen;
            saisie.san = b.moves_san || [];
            saisie.legal = b.legal_uci || [];
            saisie.invalidPly = b.invalid_ply;
            saisie.invalidMove = b.invalid_move;
            saisie.trait = b.turn;
            saisie.echec = b.in_check;
            saisie.mat = b.is_checkmate;
            saisie.pat = b.is_stalemate;
            saisie.selection = null;
            saisie.destinations = [];
            rafraichirSaisie();
        });
    }

    function rafraichirSaisie() {
        var echiquier = document.getElementById('saisie-echiquier');
        if (!echiquier || !saisie.fen) { return; }
        var figee = saisie.invalidPly !== null;
        echiquier.innerHTML = damierSaisie(saisie.fen, saisie.couleur === 'black');
        echiquier.classList.toggle('figee', figee);

        var html = '<ol>';
        for (var i = 0; i < saisie.moves.length; i += 2) {
            html += '<li class="numero">' + (i / 2 + 1) + '.</li>' + ligneCoupSaisie(i);
            html += (i + 1 < saisie.moves.length) ? ligneCoupSaisie(i + 1) : '<li></li>';
        }
        html += '</ol>';
        document.getElementById('saisie-liste').innerHTML = html +
            '<p style="color:var(--encre-doux);margin:0.6rem 0 0">' + saisie.moves.length +
            ' coup(s) — clique un coup pour tronquer à partir de là.</p>';

        var statut = document.getElementById('saisie-statut');
        if (figee) {
            statut.textContent = 'Coup ' + (saisie.invalidPly + 1) + ' (« ' + saisie.invalidMove +
                ' ») impossible à rejouer : la position est figée, tape la suite en texte.';
        } else if (saisie.mat) {
            statut.textContent = 'Échec et mat !';
        } else if (saisie.pat) {
            statut.textContent = 'Pat — la partie est nulle.';
        } else if (saisie.echec) {
            statut.textContent = 'Échec aux ' + (saisie.trait === 'white' ? 'blancs' : 'noirs') + ' !';
        } else {
            statut.textContent = 'Aux ' + (saisie.trait === 'white' ? 'blancs' : 'noirs') + '.';
        }
    }

    function ligneCoupSaisie(i) {
        var douteux = saisie.invalidPly !== null && i >= saisie.invalidPly;
        var affiche = douteux ? String(saisie.moves[i]) : saisie.san[i];
        return '<li><button type="button" class="coup' + (douteux ? ' douteux' : '') + '" data-tronque="' + i +
            '" title="Retirer à partir de ce coup">' + echapperHtml(affiche) + (douteux ? ' ⚠' : '') + '</button></li>';
    }

    /** Clic sur une case : sélection, déplacement, promotion. */
    function clicCaseSaisie(nom) {
        if (saisie.invalidPly !== null) {
            document.getElementById('saisie-statut').textContent =
                'Position figée au coup douteux : corrige la liste ci-contre pour reprendre.';
            return;
        }
        if (saisie.selection === null) {
            var carte = fenVersCarte(saisie.fen);
            var piece = carte[nom];
            if (!piece) { return; }
            var estBlanc = piece === piece.toUpperCase();
            if ((saisie.trait === 'white') !== estBlanc) {
                document.getElementById('saisie-statut').textContent =
                    'Ce n\'est pas le trait — aux ' + (saisie.trait === 'white' ? 'blancs' : 'noirs') + '.';
                return;
            }
            saisie.destinations = [];
            saisie.legal.forEach(function (m) {
                if (m.slice(0, 2) === nom && saisie.destinations.indexOf(m.slice(2, 4)) < 0) {
                    saisie.destinations.push(m.slice(2, 4));
                }
            });
            if (saisie.destinations.length === 0) { return; }
            saisie.selection = nom;
            rafraichirSaisie();
            return;
        }
        if (saisie.selection === nom) {
            saisie.selection = null;
            saisie.destinations = [];
            rafraichirSaisie();
            return;
        }
        var de = saisie.selection;
        var candidats = saisie.legal.filter(function (m) { return m.slice(0, 2) === de && m.slice(2, 4) === nom; });
        if (candidats.length === 0) {
            saisie.selection = null;
            saisie.destinations = [];
            rafraichirSaisie();
            return;
        }
        saisie.selection = null;
        saisie.destinations = [];
        if (candidats.length === 1) {
            saisie.moves.push(candidats[0]);
            apresCoupSaisie(false);
            return;
        }
        // Plusieurs coups pour le même déplacement : promotion, choix de la pièce.
        var zone = document.getElementById('saisie-promotion');
        var base = de + nom;
        zone.innerHTML = ['q', 'r', 'b', 'n'].map(function (p) {
            var pieceChar = saisie.trait === 'white' ? p.toUpperCase() : p;
            return '<button type="button" class="bouton bouton-secondaire" data-coup="' + base + p + '">' + svgPiece(pieceChar) + '</button>';
        }).join('');
        zone.hidden = false;
        Array.prototype.forEach.call(zone.querySelectorAll('button'), function (bouton) {
            bouton.addEventListener('click', function () {
                zone.hidden = true;
                zone.innerHTML = '';
                saisie.moves.push(bouton.getAttribute('data-coup'));
                apresCoupSaisie(false);
            });
        });
        rafraichirSaisie();
    }

    function envoyerSaisie(evenement) {
        evenement.preventDefault();
        var erreur = document.getElementById('saisie-erreur');
        erreur.textContent = '';
        if (saisie.moves.length === 0) {
            erreur.textContent = 'Ajoute au moins un coup avant d\'enregistrer.';
            return;
        }
        var corps = {
            moves: saisie.moves,
            validate_moves: saisie.verification,
            played_at: document.getElementById('s-date').value,
            opponent: document.getElementById('s-adversaire').value.trim(),
            opponent_rating: document.getElementById('s-classement').value === '' ? null : Number(document.getElementById('s-classement').value),
            color: document.getElementById('s-couleur').value,
            result: document.getElementById('s-resultat').value || '*',
            time_control: document.getElementById('s-cadence').value.trim() || null,
            tournament: document.getElementById('s-tournoi').value.trim() || null,
            round: document.getElementById('s-ronde').value.trim() || null
        };
        var requete = saisie.id
            ? api('/games/' + saisie.id + '/manual', { methode: 'PUT', body: corps })
            : api('/games/manual', { methode: 'POST', body: corps });
        requete.then(function (r) {
            if (r.status === 201 || r.status === 200) {
                aller('#/rejeu/' + r.body.id);
                return;
            }
            if (r.body.error === 'coup_invalide') {
                erreur.textContent = 'Le coup ' + (r.body.ply + 1) + ' (« ' + r.body.move +
                    ' ») n\'est pas légal et la vérification est active. Corrige-le dans la liste ou décoche la vérification.';
                return;
            }
            if (r.body.error === 'pas_une_saisie_manuelle') {
                erreur.textContent = 'Cette partie n\'a pas été saisie à la main : elle ne peut pas être corrigée ici.';
                return;
            }
            if (r.status === 422) {
                erreur.textContent = 'Vérifie la fiche : date et adversaire sont obligatoires.';
                return;
            }
            erreur.textContent = 'Enregistrement impossible, réessaie dans un instant.';
        });
    }

    // ------------------------------------------------ compte (W7 + W8)

    function vueCompte() {
        vue().innerHTML = '<p>Chargement…</p>';
        Promise.all([
            api('/me'),
            api('/me/settings')
        ]).then(function (reponses) {
            var moi = reponses[0], reglages = reponses[1];
            if (moi.status !== 200) { return; }
            var comptes = moi.body.linked_accounts || [];

            vue().innerHTML =
                '<h1>Mon compte</h1>' +
                '<div class="compte-grille">' +

                '  <div class="compte-carte">' +
                '    <h2>Profil</h2>' +
                '    <p style="color:var(--encre-doux)">' + echapperHtml(moi.body.email) + '</p>' +
                '    <form id="formulaire-profil">' +
                '      <div class="champ"><label for="p-nom">Nom affiché</label>' +
                '        <input id="p-nom" type="text" value="' + echapperHtml(moi.body.name || '') + '"></div>' +
                '      <div class="champ"><label for="p-club">Club</label>' +
                '        <input id="p-club" type="text" value="' + echapperHtml(moi.body.club || '') + '"></div>' +
                '      <div class="champ"><label for="p-classement">Classement (Elo)</label>' +
                '        <input id="p-classement" type="number" min="0" value="' + echapperHtml(moi.body.rating || '') + '"></div>' +
                '      <button class="bouton bouton-principal" type="submit">Enregistrer</button>' +
                '      <p class="message-ok" id="retour-profil"></p>' +
                '    </form>' +
                '  </div>' +

                '  <div class="compte-carte">' +
                '    <h2>Pseudos liés</h2>' +
                '    <ul id="liste-comptes">' +
                (comptes.length === 0 ? '<li>Aucun pseudo lié.</li>' : comptes.map(function (c) {
                    return '<li><span>' + echapperHtml(c.platform) + ' — ' + echapperHtml(c.username) + '</span>' +
                        '<button type="button" class="bouton bouton-secondaire delier" data-platform="' + echapperHtml(c.platform) +
                        '" data-username="' + echapperHtml(c.username) + '">Délier</button></li>';
                }).join('')) +
                '    </ul>' +
                '    <form id="formulaire-lier">' +
                '      <div class="champ"><label for="l-plateforme">Plateforme</label>' +
                '        <select id="l-plateforme"><option value="lichess">Lichess</option><option value="chesscom">Chess.com</option></select></div>' +
                '      <div class="champ"><label for="l-pseudo">Pseudo</label>' +
                '        <input id="l-pseudo" type="text" required></div>' +
                '      <button id="bouton-lier" class="bouton bouton-secondaire" type="submit">Lier ce pseudo</button>' +
                '      <p class="message-ok" id="retour-lier"></p>' +
                '    </form>' +
                '  </div>' +

                '  <div class="compte-carte">' +
                '    <h2>Préférences</h2>' +
                '    <form id="formulaire-reglages">' +
                '      <div class="champ"><label for="r-theme">Thème</label>' +
                '        <select id="r-theme"><option value="clair"' + (reglages.body.theme === 'clair' ? ' selected' : '') + '>Clair</option>' +
                '          <option value="sombre"' + (reglages.body.theme === 'sombre' ? ' selected' : '') + '>Sombre</option></select></div>' +
                '      <div class="champ"><label for="r-langue">Langue</label>' +
                '        <select id="r-langue"><option value="fr"' + (reglages.body.language === 'fr' ? ' selected' : '') + '>Français</option>' +
                '          <option value="en"' + (reglages.body.language === 'en' ? ' selected' : '') + '>English</option></select></div>' +
                '      <div class="champ"><label for="r-echiquier">Style d\'échiquier</label>' +
                '        <select id="r-echiquier">' +
                ['classique', 'bois', 'contraste'].map(function (s) {
                    return '<option value="' + s + '"' + (reglages.body.board_style === s ? ' selected' : '') + '>' + s + '</option>';
                }).join('') + '</select></div>' +
                '      <div class="champ"><label for="r-pieces">Style de pièces</label>' +
                '        <select id="r-pieces">' +
                ['classique', 'minimaliste'].map(function (s) {
                    return '<option value="' + s + '"' + (reglages.body.piece_style === s ? ' selected' : '') + '>' + s + '</option>';
                }).join('') + '</select></div>' +
                '      <button class="bouton bouton-principal" type="submit">Enregistrer</button>' +
                '      <p class="message-ok" id="retour-reglages"></p>' +
                '    </form>' +
                '    <p style="color:var(--encre-doux)">Abonnement : version gratuite. Les offres entraîneur et club arriveront plus tard.</p>' +
                '  </div>' +

                '  <div class="compte-carte">' +
                '    <h2>Mes données</h2>' +
                '    <p>Exporte tout ce que Korrichess sait de toi, ou supprime ton compte définitivement.</p>' +
                '    <button class="bouton bouton-secondaire" type="button" id="bouton-export">Exporter mes données (JSON)</button>' +
                '  </div>' +

                '  <div class="compte-carte zone-danger">' +
                '    <h2>Zone dangereuse</h2>' +
                '    <p>Supprimer le compte efface toutes tes parties et données, sans retour.</p>' +
                '    <div class="champ"><label for="confirmation">Tape SUPPRIMER pour confirmer</label>' +
                '      <input id="confirmation" type="text" autocomplete="off"></div>' +
                '    <button class="bouton bouton-danger" type="button" id="bouton-supprimer" disabled>Supprimer mon compte</button>' +
                '  </div>' +

                '</div>';

            document.getElementById('formulaire-profil').addEventListener('submit', function (evenement) {
                evenement.preventDefault();
                api('/me', {
                    methode: 'PATCH',
                    body: {
                        display_name: document.getElementById('p-nom').value,
                        club: document.getElementById('p-club').value,
                        rating: document.getElementById('p-classement').value === '' ? null : Number(document.getElementById('p-classement').value)
                    }
                }).then(function (r) {
                    document.getElementById('retour-profil').textContent = r.status === 200 ? 'Profil enregistré.' : 'Enregistrement impossible.';
                });
            });

            document.getElementById('formulaire-lier').addEventListener('submit', function (evenement) {
                evenement.preventDefault();
                document.getElementById('bouton-lier').disabled = true;
                api('/me/linked-accounts', {
                    methode: 'POST',
                    body: {
                        platform: document.getElementById('l-plateforme').value,
                        username: document.getElementById('l-pseudo').value
                    }
                }).then(function (r) {
                    var retour = document.getElementById('retour-lier');
                    var bouton = document.getElementById('bouton-lier');
                    if (r.status === 201) {
                        retour.innerHTML = 'Pseudo lié ! <span class="chargement"></span>Synchronisation de tes parties en cours…';
                        suivreLiaison(
                            document.getElementById('l-plateforme').value,
                            document.getElementById('l-pseudo').value,
                            Date.now(),
                            0
                        );
                    } else {
                        retour.textContent = r.body.message === 'deja_lie' ? 'Ce pseudo est déjà lié.' : 'Liaison impossible.';
                        if (bouton) { bouton.disabled = false; }
                    }
                });
            });

            // Suivi de la première synchronisation déclenchée par la liaison :
            // indicateur de chargement jusqu'à ce que le curseur du compte
            // avance (GET /sync/statut), puis retour à la vue actualisée.
            function suivreLiaison(plateforme, pseudo, depart, essais) {
                var retour = document.getElementById('retour-lier');
                if (essais >= 36) {
                    retour.textContent = 'Synchronisation toujours en cours — tes parties arriveront d\'ici peu.';
                    vueCompte();
                    return;
                }
                api('/sync/statut').then(function (res) {
                    if (res.status !== 200) {
                        setTimeout(function () { suivreLiaison(plateforme, pseudo, depart, essais + 1); }, 5000);
                        return;
                    }
                    var lignes = res.body.synchronisations || [];
                    var ligne = null;
                    for (var i = 0; i < lignes.length; i++) {
                        if (lignes[i].platform === plateforme && lignes[i].username === pseudo) { ligne = lignes[i]; break; }
                    }
                    var terminee = ligne && ligne.last_sync_at && Date.parse(ligne.last_sync_at) > depart - 60000;
                    if (terminee) {
                        retour.textContent = 'Parties importées ! Rends-toi sur ton tableau de bord pour les rejouer.';
                        setTimeout(vueCompte, 1500);
                    } else {
                        setTimeout(function () { suivreLiaison(plateforme, pseudo, depart, essais + 1); }, 5000);
                    }
                });
            }

            Array.prototype.forEach.call(document.querySelectorAll('button.delier'), function (bouton) {
                bouton.addEventListener('click', function () {
                    api('/me/linked-accounts?platform=' + encodeURIComponent(bouton.getAttribute('data-platform')) +
                        '&username=' + encodeURIComponent(bouton.getAttribute('data-username')), { methode: 'DELETE' })
                        .then(function () { vueCompte(); });
                });
            });

            document.getElementById('formulaire-reglages').addEventListener('submit', function (evenement) {
                evenement.preventDefault();
                var corps = {
                    theme: document.getElementById('r-theme').value,
                    language: document.getElementById('r-langue').value,
                    board_style: document.getElementById('r-echiquier').value,
                    piece_style: document.getElementById('r-pieces').value
                };
                api('/me/settings', { methode: 'PATCH', body: corps }).then(function (r) {
                    var retour = document.getElementById('retour-reglages');
                    retour.textContent = r.status === 200 ? 'Préférences enregistrées.' : 'Enregistrement impossible.';
                    if (r.status === 200) {
                        document.documentElement.setAttribute('data-theme', corps.theme);
                        try { localStorage.setItem('korrichess_theme', corps.theme); } catch (e) { /* stockage indisponible */ }
                    }
                });
            });

            document.getElementById('bouton-export').addEventListener('click', function () {
                api('/me/export').then(function (r) {
                    if (r.status === 200) {
                        telecharger('korrichess-export-' + new Date().toISOString().slice(0, 10) + '.json',
                            JSON.stringify(r.body, null, 2));
                    }
                });
            });

            document.getElementById('confirmation').addEventListener('input', function () {
                document.getElementById('bouton-supprimer').disabled = this.value !== 'SUPPRIMER';
            });
            document.getElementById('bouton-supprimer').addEventListener('click', function () {
                api('/me', { methode: 'DELETE' }).then(function (r) {
                    if (r.body.message === 'compte_supprime') {
                        purgerJeton();
                        location.hash = '';
                        location.reload();
                    }
                });
            });
        });
    }

    // ------------------------------------------------ routeur

    function router() {
        var hash = location.hash || '#/';
        document.body.classList.toggle('sans-session', jeton() === null);

        if (ecouteurClavier) { document.removeEventListener('keydown', ecouteurClavier); ecouteurClavier = null; }

        if (jeton() === null && hash !== '#/connexion') {
            location.hash = '#/connexion';
            return;
        }
        if (jeton() !== null && (hash === '#/' || hash === '')) {
            location.hash = '#/tableau-de-bord';
            return;
        }

        if (hash === '#/connexion') { vueConnexion(); return; }
        if (hash === '#/tableau-de-bord') { vueTableauDeBord(); return; }
        if (hash === '#/bibliotheque') { vueBibliotheque(0); return; }
        if (hash.indexOf('#/bibliotheque/') === 0) { vueBibliotheque(Number(hash.slice('#/bibliotheque/'.length)) || 0); return; }
        if (hash.indexOf('#/rejeu/') === 0) { vueRejeu(Number(hash.slice('#/rejeu/'.length))); return; }
        if (hash === '#/saisie') { vueSaisie(null); return; }
        if (hash.indexOf('#/saisie/') === 0) { vueSaisie(Number(hash.slice('#/saisie/'.length))); return; }
        if (hash === '#/compte') { vueCompte(); return; }

        location.hash = jeton() !== null ? '#/tableau-de-bord' : '#/connexion';
    }

    document.getElementById('deconnexion').addEventListener('click', function () {
        api('/auth/logout', { methode: 'POST' }).then(function () {
            purgerJeton();
            location.hash = '#/connexion';
            router();
        });
    });

    window.addEventListener('hashchange', router);
    router();
}());
