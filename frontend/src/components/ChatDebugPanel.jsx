import { useEffect, useMemo, useState } from 'react'
import './ChatDebugPanel.css'

function cfgLabel(config) {
  if (config === 'json') return 'JSON 配置'
  if (config === 'md') return 'MD 配置'
  if (config === 'api') return 'API 调用'
  if (config === 'default') return '前端默认'
  return config || '未知'
}

function Row({ k, v }) {
  return (
    <div className="dbg-row">
      <span className="dbg-k">{k}</span>
      <span className="dbg-v">{v == null || v === '' ? '—' : String(v)}</span>
    </div>
  )
}

/**
 * 调试：仅 debug 模式显示浮动小球与面板。
 * 开启方式：?debug=1 或 Ctrl/⌘+Shift+D
 */
export function ChatDebugPanel({
  enabled = false,
  open,
  onToggle,
  onClose,
  roleSlug,
  persona,
  chipMeta,
  lastCall,
  callLog,
}) {
  const [tab, setTab] = useState('overview')
  const assets = lastCall?.debug?.assets || persona?.assets || null
  const logic = lastCall?.debug?.logic

  const overview = useMemo(() => {
    const quotesTotal = assets?.quotes?.total_lines ?? '—'
    const qq = assets?.persona?.quick_questions ?? chipMeta?.quickQuestions ?? '—'
    const qp = assets?.persona?.quick_pool ?? chipMeta?.quickPool ?? '—'
    const engine = assets?.engine?.label || lastCall?.debug?.logic?.label || '—'
    return { quotesTotal, qq, qp, engine }
  }, [assets, chipMeta, lastCall])

  if (!enabled) return null

  const toggle = () => {
    if (onToggle) onToggle()
    else if (open) onClose?.()
  }

  return (
    <>
      <button
        type="button"
        className={`dbg-fab${open ? ' on' : ''}${lastCall ? ' has' : ''}`}
        onClick={toggle}
        title={open ? '关闭调试面板' : '打开调试面板'}
        aria-label={open ? '关闭调试面板' : '打开调试面板'}
        aria-expanded={open}
      >
        <span className="dbg-fab__dot" />
        <span className="dbg-fab__label">D</span>
      </button>

      {open && (
        <div className="dbg-mask" onClick={onClose} role="presentation">
          <div
            className="dbg-panel"
            role="dialog"
            aria-label="后端调用调试"
            onClick={(e) => e.stopPropagation()}
          >
            <header className="dbg-head">
              <div>
                <strong>调试 · {roleSlug}</strong>
                <small>点小球关面板 · Ctrl/⌘+Shift+D 退出 debug</small>
              </div>
              <button type="button" className="dbg-x" onClick={onClose}>
                ×
              </button>
            </header>

            <div className="dbg-tabs">
              {[
                ['overview', '总览'],
                ['last', '上次调用'],
                ['assets', '语料资产'],
                ['log', '调用历史'],
                ['raw', 'Raw'],
              ].map(([id, label]) => (
                <button
                  key={id}
                  type="button"
                  className={tab === id ? 'on' : ''}
                  onClick={() => setTab(id)}
                >
                  {label}
                </button>
              ))}
            </div>

            <div className="dbg-body">
              {tab === 'overview' && (
                <>
                  <div className="dbg-cards">
                    <div className="dbg-card">
                      <em>语录总数</em>
                      <b>{overview.quotesTotal}</b>
                      <span>quotes.json 全部分池</span>
                    </div>
                    <div className="dbg-card">
                      <em>推荐话术</em>
                      <b>
                        {overview.qq}
                        <small> +{overview.qp} 池</small>
                      </b>
                      <span>quick_questions + quick_pool</span>
                    </div>
                    <div className="dbg-card">
                      <em>当前引擎</em>
                      <b className="dbg-card__sm">{overview.engine}</b>
                      <span>离线优先级：quotes → qa.md → 本地</span>
                    </div>
                  </div>
                  <Row k="推荐展示来源" v={chipMeta?.sourceLabel} />
                  <Row k="可见 chip" v={`${chipMeta?.visible ?? 0} / 全量 ${chipMeta?.total ?? '—'}`} />
                  <Row k="上行 / 下行" v={`${chipMeta?.row1 ?? 0} + ${chipMeta?.row2 ?? 0}（下行可横滑）`} />
                  <Row k="补位队列剩余" v={chipMeta?.standby ?? 0} />
                  <Row
                    k="配置文件"
                    v={
                      assets
                        ? Object.entries(assets.files || {})
                            .map(([f, ok]) => `${ok ? '✓' : '✗'}${f}`)
                            .join('  ')
                        : '加载中/无'
                    }
                  />
                  {logic && (
                    <>
                      <Row k="上次逻辑类型" v={`${cfgLabel(logic.config)} · ${logic.label}`} />
                      <Row k="上次逻辑文件" v={logic.file} />
                    </>
                  )}
                </>
              )}

              {tab === 'last' && (
                <>
                  {!lastCall ? (
                    <p className="dbg-empty">还没有发过消息</p>
                  ) : (
                    <>
                      <Row k="接口" v={lastCall.endpoint || '—'} />
                      <Row k="source 字段" v={lastCall.source} />
                      <Row k="是否降级" v={lastCall.degraded ? '是' : '否'} />
                      <Row k="逻辑" v={logic ? `${cfgLabel(logic.config)} / ${logic.id}` : '—'} />
                      <Row k="逻辑说明" v={logic?.label} />
                      <Row k="配置文件" v={logic?.file} />
                      <Row k="命中主题" v={lastCall.matched_topic} />
                      <Row k="语录 ID" v={lastCall.quote_id} />
                      <Row k="得分" v={lastCall.score} />
                      <Row
                        k="命中关键词"
                        v={(lastCall.keys || lastCall.debug?.hit?.keys || []).join('、') || '—'}
                      />
                      <Row k="风险提示" v={lastCall.risk ? '是' : '否'} />
                      {lastCall.debug?.llm && (
                        <>
                          <Row k="LLM 成功" v={lastCall.debug.llm.ok ? '是' : '否'} />
                          <Row k="Prompt 来源" v={lastCall.debug.llm.prompt_from} />
                          <Row k="声明来源" v={lastCall.debug.llm.disclaimer_from} />
                          <Row k="Model" v={lastCall.debug.llm.model} />
                          <Row k="LLM 错误" v={lastCall.debug.llm.error} />
                          <Row k="降级到" v={lastCall.debug.llm.fallback_to} />
                        </>
                      )}
                      <Row k="用户消息" v={lastCall.userMessage} />
                      <Row
                        k="回复预览"
                        v={
                          (lastCall.reply || '').slice(0, 160) +
                          ((lastCall.reply || '').length > 160 ? '…' : '')
                        }
                      />
                    </>
                  )}
                </>
              )}

              {tab === 'assets' && (
                <>
                  {!assets ? (
                    <p className="dbg-empty">无资产统计（persona/API 未返回 assets）</p>
                  ) : (
                    <>
                      <h4>引擎</h4>
                      <Row k="优先引擎" v={assets.engine?.label} />
                      <Row k="引擎 ID" v={assets.engine?.id} />
                      <Row k="优先级链" v={(assets.engine?.priority || []).join(' → ')} />

                      <h4>quotes.json</h4>
                      <Row k="启用" v={assets.quotes?.enabled ? '是' : '否'} />
                      <Row k="总条数" v={assets.quotes?.total_lines} />
                      <Row k="内容池" v={assets.quotes?.content_lines} />
                      <Row k="元数据池(opener等)" v={assets.quotes?.meta_lines} />
                      <Row k="主题数" v={assets.quotes?.topic_count} />
                      {(assets.quotes?.topics || []).map((t) => (
                        <Row
                          key={t.id}
                          k={`${t.name} (${t.id})`}
                          v={`${t.count} · ${t.kind === 'meta' ? '拼装' : '主句'}`}
                        />
                      ))}

                      <h4>qa.md</h4>
                      <Row k="启用" v={assets.qa_md?.enabled ? '是' : '否'} />
                      <Row k="条目数" v={assets.qa_md?.entry_count} />

                      <h4>persona / 推荐</h4>
                      <Row k="persona.json" v={assets.persona?.enabled ? '是' : '否'} />
                      <Row k="quick_questions" v={assets.persona?.quick_questions} />
                      <Row k="quick_pool" v={assets.persona?.quick_pool} />
                      <Row k="推荐合计" v={assets.persona?.recommend_total} />
                      <Row k="persona.mode" v={assets.persona?.mode} />
                      <Row k="Prompt 来源" v={assets.persona?.prompt_source} />
                    </>
                  )}
                </>
              )}

              {tab === 'log' && (
                <>
                  {(callLog || []).length === 0 ? (
                    <p className="dbg-empty">暂无调用记录</p>
                  ) : (
                    <ul className="dbg-log">
                      {callLog.map((c, i) => (
                        <li key={`${c.at}-${i}`}>
                          <div className="dbg-log__t">
                            <b>{c.source}</b>
                            <span>{cfgLabel(c.debug?.logic?.config)}</span>
                            <em>{c.debug?.logic?.label || c.endpoint}</em>
                          </div>
                          <div className="dbg-log__m">
                            {c.quote_id || c.matched_topic || '—'} · {(c.userMessage || '').slice(0, 36)}
                          </div>
                        </li>
                      ))}
                    </ul>
                  )}
                </>
              )}

              {tab === 'raw' && (
                <pre className="dbg-raw">
                  {JSON.stringify(
                    {
                      chipMeta,
                      personaAssets: persona?.assets,
                      lastCall,
                      callLog,
                    },
                    null,
                    2,
                  )}
                </pre>
              )}
            </div>
          </div>
        </div>
      )}
    </>
  )
}

/** URL ?debug=1 进入 debug；Ctrl/⌘+Shift+D 开关 debug 模式 */
export function useDebugGate(setEnabled) {
  useEffect(() => {
    const params = new URLSearchParams(window.location.search)
    if (params.get('debug') === '1' || params.get('debug') === 'true') {
      setEnabled(true)
    }
    const onKey = (e) => {
      if ((e.ctrlKey || e.metaKey) && e.shiftKey && (e.key === 'D' || e.key === 'd')) {
        e.preventDefault()
        setEnabled((v) => !v)
      }
    }
    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [setEnabled])
}
