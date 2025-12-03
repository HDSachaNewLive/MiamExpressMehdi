<!-- slider_son.php -->
<div id="volume-widget">
  <button id="volume-button">🔊</button>
  <input type="range" id="volume-slider" min="0" max="1" step="0.01" value="1">
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const audio = document.getElementById("player");
    const slider = document.getElementById("volume-slider");
    const btn = document.getElementById("volume-button");

    if(!audio) return; // si audio introuvable, on sort

    // initialisation du volume
    audio.volume = parseFloat(slider.value);

    // slider change le volume en direct
    slider.addEventListener("input", () => {
        audio.volume = parseFloat(slider.value);
        btn.textContent = audio.volume > 0 ? "🔊" : "🔇";
    });

    // bouton mute/unmute
    btn.addEventListener("click", () => {
        if(audio.volume > 0){
            btn.dataset.lastVol = audio.volume;
            audio.volume = 0;
            slider.value = 0;
            btn.textContent = "🔇";
        } else {
            let restore = parseFloat(btn.dataset.lastVol || 1);
            audio.volume = restore;
            slider.value = restore;
            btn.textContent = "🔊";
        }
    });
});
</script>

<style>
/* Container du widget */
#volume-widget {
    position: fixed;
    top: 15px;
    right: 20px;
    z-index: 99999;
    background: rgba(255, 255, 255, 0.15);
    backdrop-filter: blur(15px);
    padding: 0.7rem 1rem;
    border-radius: 1.5rem;
    display: flex;
    align-items: center;
    gap: 12px;
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
    transform: translateY(-10px);
    opacity: 0;
    animation: fadeIn 0.4s ease forwards;
}

/* Bouton */
#volume-button {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 40px;
    height: 40px;
    font-size: 1.2rem;
    background: linear-gradient(135deg, #ff6b6b, #ffc342);
    border: none;
    border-radius: 14px;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(0,0,0,0.18);
    transition: all 0.35s ease;
    position: relative;
    overflow: hidden;
}

#volume-button::after {
    content: "";
    position: absolute;
    top: 0; left: -100%;
    width: 100%; height: 100%;
    background: rgba(255,255,255,0.25);
    transition: all 0.4s ease;
    border-radius: 14px;
}

#volume-button:hover::after {
    left: 0;
}

#volume-button:hover {
    transform: scale(1.1);
    box-shadow: 0 12px 25px rgba(0,0,0,0.25);
}

/* Slider */
#volume-slider {
    width: 150px;
    -webkit-appearance: none;
    appearance: none;
    height: 8px;
    border-radius: 10px;
    background: linear-gradient(135deg, #ff6b6b, #ffc342);
    outline: none;
    transition: all 0.3s ease;
}

#volume-slider::-webkit-slider-thumb {
    -webkit-appearance: none;
    appearance: none;
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    transition: all 0.3s ease;
}

#volume-slider::-webkit-slider-thumb:hover {
    transform: scale(1.1);
}

#volume-slider::-moz-range-thumb {
    width: 20px;
    height: 20px;
    border-radius: 50%;
    background: #fff;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.25);
    transition: all 0.3s ease;
}

/* Animation fadeIn */
@keyframes fadeIn {
    to { transform: translateY(0); opacity: 1; }
}
</style>
<!-- fin slider_son.php -->
