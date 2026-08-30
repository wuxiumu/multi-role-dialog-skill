import { useEffect } from 'react'
import { SITE, absoluteUrl } from './site.js'

function setMeta(attr, key, content) {
  if (content == null || content === '') return
  let el = document.head.querySelector(`meta[${attr}="${key}"]`)
  if (!el) {
    el = document.createElement('meta')
    el.setAttribute(attr, key)
    document.head.appendChild(el)
  }
  el.setAttribute('content', content)
}

function setLink(rel, href) {
  if (!href) return
  let el = document.head.querySelector(`link[rel="${rel}"]`)
  if (!el) {
    el = document.createElement('link')
    el.setAttribute('rel', rel)
    document.head.appendChild(el)
  }
  el.setAttribute('href', href)
}

/**
 * 按路由更新 title / description / canonical / Open Graph。
 * SPA 爬虫可读性有限，静态 index.html 仍保留完整默认 meta。
 */
export function Seo({
  title,
  description = SITE.description,
  path = '/',
  noIndex = false,
  type = 'website',
  image,
}) {
  useEffect(() => {
    const fullTitle = title
      ? title.includes(SITE.name)
        ? title
        : `${title} · ${SITE.name}`
      : SITE.titleDefault
    const desc = description || SITE.description
    const url = absoluteUrl(path)
    const img = image || absoluteUrl('/og.png')

    document.title = fullTitle
    setMeta('name', 'description', desc)
    setMeta('name', 'robots', noIndex ? 'noindex,nofollow' : 'index,follow')
    setLink('canonical', url)

    setMeta('property', 'og:title', fullTitle)
    setMeta('property', 'og:description', desc)
    setMeta('property', 'og:url', url)
    setMeta('property', 'og:type', type)
    setMeta('property', 'og:image', img)
    setMeta('property', 'og:site_name', SITE.name)
    setMeta('property', 'og:locale', SITE.locale)

    setMeta('name', 'twitter:card', 'summary_large_image')
    setMeta('name', 'twitter:title', fullTitle)
    setMeta('name', 'twitter:description', desc)
    setMeta('name', 'twitter:image', img)
  }, [title, description, path, noIndex, type, image])

  return null
}
