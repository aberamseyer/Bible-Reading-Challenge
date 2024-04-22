function random(min, max) {
  min = Math.ceil(min);
  max = Math.floor(max);
  return Math.floor(Math.random() * (max - min + 1)) + min;
}

const emojis = document.querySelectorAll('.emoji')
const mountainWrap = document.querySelector('.mountain-wrap')

setTimeout(() => {
  const yRange = [0, Math.floor(mountainWrap.clientHeight / 250 * 100) - 12] // target % is 88
  emojis.forEach(em => {
    const delay = random(0, 1500)
    const progress = parseFloat(em.getAttribute('data-percent')) / 100
    const time = progress * Math.log(progress * 5) + 1.5
    setTimeout(() => {
      em.classList.add('animated')
      em.style.transition = `${Math.round(time*10)/10}s all`
      em.style.bottom = (yRange[1]*progress + (1-progress)*random(-0.5, 0.5)) + '%'
      em.style.left = (50 + (1-progress)*random(-15, 15)) + '%'
      em.children[0].style.animation = `yAxis 0.2s ${Math.round(time / 0.2)} cubic-bezier(0.02, 0.01, 0.21, 1)`
      setTimeout(() => {
        em.classList.remove('animated')
        em.style.transition = `.2s all`
        em.children[0].style.animation = ``
      }, time * 1000)
    }, delay)
  })
}, 500)

// emoji mouse repulsion
mountainWrap.addEventListener('mousemove', e => {
  const mouseX = e.clientX
  const mouseY = e.clientY

  emojis.forEach(em => {
    const rect = em.getBoundingClientRect();
    const elementX = rect.left + rect.width / 2
    const elementY = rect.top + rect.height / 2

    const deltaX = mouseX - elementX
    const deltaY = mouseY - elementY

    const distance = Math.sqrt(deltaX * deltaX + deltaY * deltaY)
    const repelStrength = 10

    if (distance < 25) {
      const forceX = -repelStrength * (deltaX / distance)
      const forceY = -repelStrength * (deltaY / distance)

      em.children[0].style.transform = `translate(${forceX}px, ${forceY}px)`
    }
    else {
      em.children[0].style.transform = 'none'
    }
  })
})