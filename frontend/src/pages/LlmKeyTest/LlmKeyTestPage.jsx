import { useEffect, useMemo, useState } from 'react'
import { Link, Navigate } from 'react-router-dom'
import { useAuth } from '../../store/AuthContext.jsx'
import { fetchLlmProviders, testLlmKey, LLM_MANUAL_PRESETS } from '../../api/llm.js'
import { toast } from '../../components/Toast.jsx'
import './LlmKeyTestPage.css'

export function LlmKeyTestPage() {
  const { isLoggedIn, token } = useAuth()
  const [mode, setMode] = useState('system')
  const [providers, setProviders] = useState([])
  const [providerId, setProviderId] = useState('')
  const [baseUrl, setBaseUrl] = useState(LLM_MANUAL_PRESETS[0].base_url)
  const [apiKey, setApiKey] = useState('')
  const [model, setModel] = useState(LLM_MANUAL_PRESETS[0].model)
  const [prompt, setPrompt] = useState('请只回复两个字：通过')
  const [loadingProviders, setLoadingProviders] = useState(true)
  const [testing, setTesting] = useState(false)
  const [result, setResult] = useState(null)

  useEffect(() => {
    let alive = true
    ;(async () => {
      setLoadingProviders(true)
      const data = await fetchLlmProviders()
      if (!alive) return
      const list = (data.providers || []).filter((p) => p.id !== 'none')
      setProviders(list)
      const firstReady = list.find((p) => p.ready !== false)
      setProviderId(firstReady?.id || list[0]?.id || '')
      setLoadingProviders(false)
    })()
    return () => {
      alive = false
    }
  }, [])

  const selectedSystem = useMemo(
    () => providers.find((p) => p.id === providerId) || null,
    [providers, providerId],
  )

  if (!isLoggedIn) {
    return <Navigate to="/login" replace />
  }

  function applyPreset(preset) {
    setBaseUrl(preset.base_url)
    setModel(preset.model)
    toast(`已填入预设：${preset.name}`, 'ok')
  }

  async function onTest() {
    setTesting(true)
    setResult(null)
    try {
      const data =
        mode === 'system'
          ? await testLlmKey({ mode: 'system', provider: providerId, prompt, token })
          : await testLlmKey({
              mode: 'manual',
              base_url: baseUrl.trim(),
              api_key: apiKey.trim(),
              model: model.trim(),
              prompt,
              token,
            })
      setResult(data)
      if (data?.ok) toast('验证通过', 'ok')
      else toast(data?.error || '验证失败', 'error')
    } catch (e) {
      const msg = e?.message || '请求失败'
      setResult({ ok: false, error: msg, mode })
      toast(msg, 'error')
    } finally {
      setTesting(false)
    }
  }

  const canSubmit =
    !testing &&
    (mode === 'system'
      ? !!providerId && selectedSystem && selectedSystem.ready !== false
      : !!(baseUrl.trim() && apiKey.trim() && model.trim()))

  return (
    <main className="keytest">
      <div className="keytest__card">
        <p className="keytest__eyebrow">LLM Lab</p>
        <h1>大模型 Key 测试</h1>
        <p className="keytest__lead">
          可用系统已配置模型，或手动填写 Base URL / API Key / Model，发一次极短对话验证连通性。不会写入角色
          cache，也不会回显完整密钥。
        </p>

        <div className="keytest__tabs" role="tablist" aria-label="测试方式">
          <button
            type="button"
            role="tab"
            aria-selected={mode === 'system'}
            className={mode === 'system' ? 'on' : ''}
            onClick={() => {
              setMode('system')
              setResult(null)
            }}
          >
            系统模型
          </button>
          <button
            type="button"
            role="tab"
            aria-selected={mode === 'manual'}
            className={mode === 'manual' ? 'on' : ''}
            onClick={() => {
              setMode('manual')
              setResult(null)
            }}
          >
            手动输入
          </button>
        </div>

        {mode === 'system' ? (
          <section className="keytest__section">
            <h2>选择系统已配置模型</h2>
            {loadingProviders ? (
              <p className="keytest__muted">加载模型列表…</p>
            ) : providers.length === 0 ? (
              <p className="keytest__muted">暂无可用系统模型，请先在 backend 配置，或改用手动输入。</p>
            ) : (
              <div className="keytest__providers" role="radiogroup">
                {providers.map((p) => {
                  const active = providerId === p.id
                  const disabled = p.ready === false
                  return (
                    <button
                      key={p.id}
                      type="button"
                      role="radio"
                      aria-checked={active}
                      disabled={disabled}
                      className={`keytest__provider${active ? ' on' : ''}${disabled ? ' off' : ''}`}
                      onClick={() => setProviderId(p.id)}
                    >
                      <span className="keytest__provider-name">
                        {p.name}
                        {disabled ? <em className="warn">未就绪</em> : null}
                        {active && !disabled ? <em>已选</em> : null}
                      </span>
                      <span className="keytest__provider-desc">{p.desc || p.model || p.id}</span>
                    </button>
                  )
                })}
              </div>
            )}
          </section>
        ) : (
          <section className="keytest__section">
            <h2>手动填写（OpenAI 兼容）</h2>
            <div className="keytest__presets">
              {LLM_MANUAL_PRESETS.map((p) => (
                <button key={p.id} type="button" className="keytest__chip" onClick={() => applyPreset(p)}>
                  {p.name}
                </button>
              ))}
            </div>

            <label className="keytest__field">
              <span>Base URL</span>
              <input
                value={baseUrl}
                onChange={(e) => setBaseUrl(e.target.value)}
                placeholder="https://api.example.com/v1"
                autoComplete="off"
                spellCheck={false}
              />
            </label>
            <label className="keytest__field">
              <span>API Key</span>
              <input
                type="password"
                value={apiKey}
                onChange={(e) => setApiKey(e.target.value)}
                placeholder="sk-..."
                autoComplete="off"
                spellCheck={false}
              />
            </label>
            <label className="keytest__field">
              <span>Model</span>
              <input
                value={model}
                onChange={(e) => setModel(e.target.value)}
                placeholder="deepseek-chat / qwen-plus / gpt-4o-mini"
                autoComplete="off"
                spellCheck={false}
              />
            </label>
          </section>
        )}

        <label className="keytest__field">
          <span>探测提示词（可选）</span>
          <input value={prompt} onChange={(e) => setPrompt(e.target.value)} maxLength={200} />
        </label>

        <div className="keytest__actions">
          <button type="button" className="btn btn--brand" disabled={!canSubmit} onClick={onTest}>
            {testing ? '验证中…' : '开始验证'}
          </button>
          <Link to="/profile" className="btn btn--ghost">
            回个人中心
          </Link>
        </div>

        {result && (
          <section className={`keytest__result${result.ok ? ' ok' : ' bad'}`} aria-live="polite">
            <h2>{result.ok ? '验证通过' : '验证失败'}</h2>
            <dl>
              <div>
                <dt>模式</dt>
                <dd>{result.mode === 'manual' ? '手动输入' : '系统模型'}</dd>
              </div>
              <div>
                <dt>供应商</dt>
                <dd>{result.provider_name || result.provider || '—'}</dd>
              </div>
              <div>
                <dt>模型</dt>
                <dd>{result.model || '—'}</dd>
              </div>
              <div>
                <dt>Host</dt>
                <dd>{result.endpoint_host || result.detail?.endpoint_host || '—'}</dd>
              </div>
              <div>
                <dt>Key</dt>
                <dd className="mono">{result.api_key_masked || '—'}</dd>
              </div>
              {result.detail?.latency_ms != null && result.detail.latency_ms !== '' && (
                <div>
                  <dt>耗时</dt>
                  <dd>{result.detail.latency_ms} ms</dd>
                </div>
              )}
              {result.detail?.http_code != null && result.detail.http_code !== '' && (
                <div>
                  <dt>HTTP</dt>
                  <dd>{result.detail.http_code}</dd>
                </div>
              )}
              {result.detail?.total_tokens != null && result.detail.total_tokens !== '' && (
                <div>
                  <dt>Tokens</dt>
                  <dd>{result.detail.total_tokens}</dd>
                </div>
              )}
              {result.ok ? (
                <div className="full">
                  <dt>模型回复</dt>
                  <dd>{result.reply || '（空）'}</dd>
                </div>
              ) : (
                <div className="full">
                  <dt>错误</dt>
                  <dd className="err">{result.error || '未知错误'}</dd>
                </div>
              )}
            </dl>
          </section>
        )}
      </div>
    </main>
  )
}
